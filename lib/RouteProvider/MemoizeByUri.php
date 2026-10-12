<?php

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

use function array_chunk;
use function array_combine;
use function array_diff_key;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function implode;
use function iterator_to_array;
use function max;
use function preg_match;
use function round;
use function spl_object_id;
use function substr;
use function substr_count;

/**
 * Speed up route resolution for predicate {@see ByUri}.
 */
final class MemoizeByUri implements RouteProvider
{
    public function __construct(
        private readonly RouteProvider $inner_provider
    ) {
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByUri) {
            return $this->route_for_uri($predicate);
        }

        return $this->inner_provider->route_for_predicate($predicate);
    }

    /**
     * @var Route[]
     */
    private array $routes;

    public function getIterator(): Traversable
    {
        return new ArrayIterator(
            $this->routes ??= array_values(iterator_to_array($this->inner_provider->getIterator()))
        );
    }

    private function route_for_uri(ByUri $predicate): ?Route
    {
        $path = $predicate->path;
        $method = $predicate->method;
        $path_params = [];

        [ $static, $dynamic ] = $this->sort_routes();

        $route = null;

        foreach ($static[$path] ?? [] as $candidate) {
            if ($candidate->method_matches($method)) {
                $route = $candidate;
                break;
            }
        }

        if (!$route && $dynamic) {
            $route = $this->route_for_dynamic_path($path, $method, $path_params);
        }

        if (!$route) {
            return null;
        }

        // We update the predicate with the path parameters and remove matches from the query parameters.

        $predicate->path_params = $path_params;

        if ($predicate->query_params) {
            $predicate->query_params = array_diff_key($predicate->query_params, $path_params);
        }

        return $route;
    }

    /**
     * Number of dynamic routes combined in a single regex. Larger chunks mean fewer `preg_match()`
     * calls but a larger regex to run when a route is at the end of the chunk.
     */
    private const int CHUNK_SIZE = 30;

    /**
     * @var array<array{ regex: string, routes: Route[] }>|null
     */
    private ?array $chunks = null;

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
     */
    private function route_for_dynamic_path(string $path, RequestMethod $method, array|null &$path_params): ?Route
    {
        $this->chunks ??= $this->make_chunks($this->sort_routes()[1]);

        foreach ($this->chunks as [ 'regex' => $regex, 'routes' => $routes ]) {
            $matched = preg_match($regex, $path, $matches);

            if ($matched === 0) {
                continue;
            }

            $first = 0;

            if ($matched === 1) {
                $mark = (int)$matches['MARK'];
                $route = $routes[$mark];

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
            for ($i = $first, $n = count($routes); $i < $n; $i++) {
                $route = $routes[$i];

                if ($route->method_matches($method) && $route->pattern->matches($path, $path_params)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Combines the dynamic routes into chunks, each with a single regex.
     *
     * The regex of a route is `#^…$#`, we use the part between the delimiters.
     * The branch reset group `(?|` makes the captures of each alternative start at 1.
     *
     * @param Route[] $dynamic
     *
     * @return array<array{ regex: string, routes: Route[] }>
     */
    private function make_chunks(array $dynamic): array
    {
        if (!$dynamic) {
            return [];
        }

        $dynamic = array_values($dynamic);
        $count = count($dynamic);
        $size = max(1, (int)ceil($count / max(1, round($count / self::CHUNK_SIZE))));
        $chunks = [];

        foreach (array_chunk($dynamic, $size) as $routes) {
            $alternatives = [];

            foreach ($routes as $i => $route) {
                $alternatives[] = substr($route->pattern->regex, 2, -2) . "(*:$i)";
            }

            $chunks[] = [ 'regex' => '#^(?|' . implode('|', $alternatives) . ')$#', 'routes' => $routes ];
        }

        return $chunks;
    }

    /**
     * @var array<string, Route[]>|null
     *     Where _key_ is the path of the static routes, in declaration order.
     */
    private ?array $static = null;

    /**
     * @var Route[]|null
     */
    private ?array $dynamic = null;

    private const string PATH_SEPARATOR = '/';

    /**
     * Sort routes according to their type and computed weight.
     *
     * Routes and grouped in two groups: static routes and dynamic routes. The difference between
     * static and dynamic routes is that dynamic routes capture parameters from the path and thus
     * require a regex to compute the matches, whereas static routes only require a simple string
     * comparison. Static routes are indexed by path, so they are found with a single array lookup.
     *
     * Dynamic routes are ordered according to their weight, which is computed from the number
     * of static parts before the first capture. The more static parts, the lighter the route is.
     *
     * @return array{0: array<string, Route[]>, 1: Route[]} An array with the static routes indexed by
     * path, and the dynamic routes.
     */
    private function sort_routes(): array
    {
        $static = $this->static;
        $dynamic = $this->dynamic;

        if ($static !== null && $dynamic !== null) {
            return [ $static, $dynamic ];
        }

        $static = [];
        $dynamic = [];
        $weights = [];

        /* @var Route $route */

        foreach ($this as $route) {
            $pattern = $route->pattern;

            if (!count($pattern->params)) {
                $static[$pattern->pattern][] = $route;
            } else {
                $dynamic[] = $route;
                $weights[spl_object_id($route)] = substr_count(
                    $pattern->interleaved[0], // @phpstan-ignore-line
                    self::PATH_SEPARATOR
                );
            }
        }

        uasort(
            $dynamic,
            // the comparison needs to be reversed to transform weights into priorities
            fn(Route $a, Route $b): int => $weights[spl_object_id($b)] <=> $weights[spl_object_id($a)]
        );

        return [ $this->static = $static, $this->dynamic = $dynamic ];
    }
}
