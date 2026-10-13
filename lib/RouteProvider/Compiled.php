<?php

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ReflectionClass;
use ReflectionException;
use Traversable;

use function array_chunk;
use function array_combine;
use function array_diff_key;
use function array_find;
use function array_keys;
use function array_map;
use function array_slice;
use function ceil;
use function count;
use function implode;
use function is_array;
use function max;
use function preg_match;
use function round;
use function substr;
use function substr_count;
use function usort;

/**
 * Speed up route resolution for predicates {@see ById}, {@see ByAction}, {@see ByIdOrAction}, and {@see ByUri}.
 *
 * **This provider is meant for production.** Compiling routes is a build-time step, it should happen while
 * warming up the application (e.g. before the Docker image is built), so that requests only load the result and
 * never compile. In development, where routes change often, prefer {@see Mutable} or {@see Immutable}, possibly
 * decorated with {@see Memoize}.
 *
 * The routes of the inner provider are compiled when the instance is created, and the inner provider is not
 * kept. The compiled data is made of arrays, which means that the instance can be exported with `var_export()`
 * and restored with {@see __set_state()} without compiling again, e.g. as a configuration value cached as a
 * PHP file that OPcache shares between requests.
 *
 * The routes are stored once, in declaration order, and the indexes refer to them by position. Routes are
 * created on demand, only the ones that are actually needed.
 *
 * - `ById` and `ByAction`: lookups are indexed. As with {@see MemoizeById} and {@see MemoizeByAction}, the last
 *   route wins.
 * - `ByUri`: routes are grouped in static routes and dynamic routes. Static routes are indexed by path, so they
 *   are found with a single array lookup. Dynamic routes capture parameters from the path and thus require a
 *   regex. They are ordered according to their weight, which is computed from the number of static parts
 *   before the first capture (the more static parts, the lighter the route is), and combined by chunks of about
 *   {@see CHUNK_SIZE} routes, with a single regex per chunk.
 * - Other predicates are tested against every route.
 *
 * @phpstan-type RouteData array{
 *     pattern: array{ pattern: string, interleaved: array<string|string[]>, params: array<int, int|string>, regex: string },
 *     action: non-empty-string,
 *     methods: string|string[],
 *     id: string|null,
 *     extensions: object[],
 * }
 */
final class Compiled implements RouteProvider
{
    private const string PATH_SEPARATOR = '/';

    /**
     * Number of dynamic routes combined in a single regex. Larger chunks mean fewer `preg_match()`
     * calls but a larger regex to run when a route is at the end of the chunk.
     */
    private const int CHUNK_SIZE = 30;

    /**
     * @param array{
     *     routes: RouteData[],
     *     by_id: array<string, int>,
     *     by_action: array<string, int>,
     *     static: array<string, int[]>,
     *     chunks: array<array{ regex: string, routes: int[] }>,
     * } $an_array
     *
     * @throws ReflectionException
     */
    public static function __set_state(array $an_array): self
    {
        /* @var self $instance */
        $instance = new ReflectionClass(self::class)->newInstanceWithoutConstructor();
        $instance->routes = $an_array['routes'];
        $instance->by_id = $an_array['by_id'];
        $instance->by_action = $an_array['by_action'];
        $instance->static = $an_array['static'];
        $instance->chunks = $an_array['chunks'];

        return $instance;
    }

    /**
     * @var RouteData[]
     */
    private array $routes = [];

    /**
     * @var array<string, int>
     *     Where _key_ is a route identifier and _value_ the position of the route.
     */
    private array $by_id = [];

    /**
     * @var array<string, int>
     *     Where _key_ is a route action and _value_ the position of the route.
     */
    private array $by_action = [];

    /**
     * @var array<string, int[]>
     *     Where _key_ is a path and _value_ the positions of the routes with that path.
     */
    private array $static = [];

    /**
     * @var array<array{ regex: string, routes: int[] }>
     *     Dynamic routes combined by chunks, with the positions of their routes.
     */
    private array $chunks = [];

    /**
     * @var Route[]
     *     Routes created so far, where _key_ is the position of the route. They are not exported.
     */
    private array $instances = [];

    /**
     * @param RouteProvider $provider
     *     The routes to compile. The provider is not kept.
     */
    public function __construct(RouteProvider $provider)
    {
        /** @var list<Route> $routes */
        $routes = [ ...$provider ];
        $dynamic = [];
        $weights = [];

        foreach ($routes as $i => $route) {
            $pattern = $route->pattern;
            $methods = $route->methods;

            $this->routes[] = [
                'pattern' => [
                    'pattern' => $pattern->pattern,
                    'interleaved' => $pattern->interleaved,
                    'params' => $pattern->params,
                    'regex' => $pattern->regex,
                ],
                'action' => $route->action,
                'methods' => is_array($methods) ? array_map(fn(RequestMethod $m) => $m->value, $methods) : $methods->value,
                'id' => $route->id,
                'extensions' => $route->extensions,
            ];

            // Same semantics as MemoizeById and MemoizeByAction, the last route wins.
            if ($route->id) {
                $this->by_id[$route->id] = $i;
            }

            $this->by_action[$route->action] = $i;

            if (!count($pattern->params)) {
                $this->static[$pattern->pattern][] = $i;
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

        $this->chunks = self::make_chunks($routes, $dynamic);
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

    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_map($this->route_at(...), array_keys($this->routes)));
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByUri) {
            return $this->route_for_uri($predicate);
        }

        if ($predicate instanceof ById) {
            return $this->route_at_or_null($this->by_id[$predicate->id] ?? null);
        }

        if ($predicate instanceof ByAction) {
            return $this->route_at_or_null($this->by_action[$predicate->action] ?? null);
        }

        if ($predicate instanceof ByIdOrAction) {
            return $this->route_at_or_null(
                $this->by_id[$predicate->id_or_action]
                ?? $this->by_action[$predicate->id_or_action]
                ?? null
            );
        }

        return array_find([ ...$this ], $predicate);
    }

    /**
     * Finds the first route matching the predicate. On success, the path parameters are captured in
     * the predicate, and removed from its query parameters.
     */
    private function route_for_uri(ByUri $predicate): ?Route
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
        foreach ($this->static[$path] ?? [] as $i) {
            $route = $this->route_at($i);

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
        foreach ($this->chunks as [ 'regex' => $regex, 'routes' => $indexes ]) {
            $matched = preg_match($regex, $path, $matches);

            if ($matched === 0) {
                continue;
            }

            $first = 0;

            if ($matched === 1) {
                $mark = (int)$matches['MARK'];
                $route = $this->route_at($indexes[$mark]);

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
                $route = $this->route_at($indexes[$i]);

                if ($route->method_matches($method) && $route->pattern->matches($path, $path_params)) {
                    return $route;
                }
            }
        }

        $path_params = [];

        return null;
    }

    private function route_at_or_null(int|null $position): ?Route
    {
        return $position === null ? null : $this->route_at($position);
    }

    private function route_at(int $position): Route
    {
        return $this->instances[$position] ??= $this->make_route($this->routes[$position]);
    }

    /**
     * @param RouteData $data
     */
    private function make_route(array $data): Route
    {
        $methods = $data['methods'];

        return new Route(
            Pattern::__set_state($data['pattern']),
            $data['action'],
            is_array($methods) ? array_map(RequestMethod::from(...), $methods) : RequestMethod::from($methods),
            $data['id'],
            $data['extensions'],
        );
    }
}
