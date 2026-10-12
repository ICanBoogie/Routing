<?php

namespace ICanBoogie\Routing\RouteProvider;

use Closure;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;

use function array_chunk;
use function array_combine;
use function array_diff_key;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function implode;
use function max;
use function preg_match;
use function round;
use function substr;
use function substr_count;
use function usort;

/**
 * Finds the route matching a {@see ByUri} predicate.
 *
 * The index is made of plain data (see {@see compile()}), so that it can be exported to a file and loaded through
 * OPcache. Routes are referred to by their position in the list of routes, and are only retrieved when they
 * are candidates for the match.
 *
 * Routes are grouped in two groups: static routes and dynamic routes. The difference between static and dynamic routes
 * is that dynamic routes capture parameters from the path and thus require a regex to compute the matches, whereas
 * static routes only require a simple string comparison. Static routes are indexed by path, so they are found with
 * a single array lookup.
 *
 * Dynamic routes are ordered according to their weight, which is computed from the number of static parts before the
 * first capture. The more static parts, the lighter the route is. They are then combined by chunks of
 * about {@see CHUNK_SIZE} routes, with a single regex per chunk.
 *
 * @internal
 *
 * @phpstan-type IndexData array{
 *     static: array<string, int[]>,
 *     chunks: array<array{ regex: string, routes: int[] }>
 * }
 */
final class ByUriIndex
{
    private const string PATH_SEPARATOR = '/';

    /**
     * Number of dynamic routes combined in a single regex. Larger chunks mean fewer `preg_match()`
     * calls but a larger regex to run when a route is at the end of the chunk.
     */
    private const int CHUNK_SIZE = 30;

    /**
     * @param Route[] $routes
     *
     * @return IndexData
     */
    public static function compile(array $routes): array
    {
        $static = [];
        $dynamic = [];
        $weights = [];

        foreach ($routes as $i => $route) {
            $pattern = $route->pattern;

            if (!count($pattern->params)) {
                $static[$pattern->pattern][] = $i;
            } else {
                $dynamic[] = $i;
                $weights[$i] = substr_count(
                    $pattern->interleaved[0], // @phpstan-ignore-line
                    self::PATH_SEPARATOR
                );
            }
        }

        // the comparison needs to be reversed to transform weights into priorities
        usort($dynamic, fn(int $a, int $b): int => $weights[$b] <=> $weights[$a]);

        return [ 'static' => $static, 'chunks' => self::make_chunks($routes, $dynamic) ];
    }

    /**
     * Combines the dynamic routes into chunks, each with a single regex.
     *
     * The regex of a route is `#^…$#`, we use the part between the delimiters.
     * The branch reset group `(?|` makes the captures of each alternative start at 1.
     *
     * @param Route[] $routes
     * @param int[] $dynamic
     *
     * @return array<array{ regex: string, routes: int[] }>
     */
    private static function make_chunks(array $routes, array $dynamic): array
    {
        if (!$dynamic) {
            return [];
        }

        $count = count($dynamic);
        $size = max(1, (int)ceil($count / max(1, round($count / self::CHUNK_SIZE))));
        $chunks = [];

        foreach (array_chunk($dynamic, $size) as $indexes) {
            $alternatives = [];

            foreach ($indexes as $mark => $i) {
                $alternatives[] = substr($routes[$i]->pattern->regex, 2, -2) . "(*:$mark)";
            }

            $chunks[] = [ 'regex' => '#^(?|' . implode('|', $alternatives) . ')$#', 'routes' => $indexes ];
        }

        return $chunks;
    }

    /**
     * @param IndexData $data
     * @param Closure(int): Route $route_at
     *     Returns the route at the specified position.
     */
    public function __construct(
        private readonly array $data,
        private readonly Closure $route_at,
    ) {
    }

    /**
     * Finds the first route matching the predicate. On success, the path parameters are captured in
     * the predicate, and removed from its query parameters.
     */
    public function find(ByUri $predicate): ?Route
    {
        $path = $predicate->path;
        $method = $predicate->method;
        $path_params = [];

        $route = $this->find_static($path, $method)
            ?? $this->find_dynamic($path, $method, $path_params);

        if (!$route) {
            return null;
        }

        $predicate->path_params = $path_params;

        if ($predicate->query_params) {
            $predicate->query_params = array_diff_key($predicate->query_params, $path_params);
        }

        return $route;
    }

    private function find_static(string $path, RequestMethod $method): ?Route
    {
        foreach ($this->data['static'][$path] ?? [] as $i) {
            $route = ($this->route_at)($i);

            if ($route->method_matches($method)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Finds the first dynamic route matching the path and method.
     *
     * Each chunk of routes is tested with a single `preg_match()`: the alternatives are
     * tried in order, and the `MARK` of the one that matched is the index of the route in the chunk.
     * If the method of that route doesn't match, the following routes of the chunk are tested one by one,
     * since the routes before it are known not to match the path. If the regex cannot run, all the
     * routes of the chunk are tested one by one.
     *
     * @param array<int|string, string>|null $path_params
     * @param-out array<int|string, string> $path_params
     */
    private function find_dynamic(string $path, RequestMethod $method, array|null &$path_params): ?Route
    {
        foreach ($this->data['chunks'] as [ 'regex' => $regex, 'routes' => $indexes ]) {
            $matched = preg_match($regex, $path, $matches);

            if ($matched === 0) {
                continue;
            }

            $first = 0;

            if ($matched === 1) {
                $mark = (int)$matches['MARK'];
                $route = ($this->route_at)($indexes[$mark]);

                if ($route->method_matches($method)) {
                    $path_params = array_combine(
                        $route->pattern->params,
                        array_slice($matches, 1, count($route->pattern->params))
                    );

                    return $route;
                }

                $first = $mark + 1;
            }

            // The regex failed to run (e.g. PCRE JIT stack limit), we cannot trust it and test the routes one by one.
            for ($i = $first, $n = count($indexes); $i < $n; $i++) {
                $route = ($this->route_at)($indexes[$i]);

                if ($route->method_matches($method) && $route->pattern->matches($path, $path_params)) {
                    return $route;
                }
            }
        }

        $path_params = [];

        return null;
    }
}
