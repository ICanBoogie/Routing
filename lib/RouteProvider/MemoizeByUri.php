<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

use function array_diff_key;
use function array_values;
use function count;
use function iterator_to_array;
use function spl_object_id;
use function substr_count;

/**
 * Speed up route resolution for predicate {@link ByUri}.
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

        /**
         * Search for a matching static respond.
         *
         * @param Route[] $routes
         */
        $map_static = function (iterable $routes) use ($path, $method): ?Route {
            foreach ($routes as $route) {
                $pattern = (string) $route->pattern;

                if ($route->method_matches($method) && $pattern === $path) {
                    return $route;
                }
            }

            return null;
        };

        /**
         * Search for a matching dynamic respond.
         *
         * @param Route[] $routes
         */
        $map_dynamic = function (iterable $routes) use ($path, $method, &$path_params): ?Route {
            foreach ($routes as $route) {
                $pattern = $route->pattern;

                if (!$route->method_matches($method) || !$pattern->matches($path, $path_params)) {
                    continue;
                }

                return $route;
            }

            return null;
        };

        [ $static, $dynamic ] = $this->sort_routes();

        $route = null;

        if ($static) {
            $route = $map_static($static);
        }

        if (!$route && $dynamic) {
            $route = $map_dynamic($dynamic);
        }

        if (!$route) {
            return null;
        }

        // We update the predicate with the path parameters, and remove matches from the query parameters.

        $predicate->path_params = $path_params;

        if ($predicate->query_params) {
            $predicate->query_params = array_diff_key($predicate->query_params, $path_params);
        }

        return $route;
    }

    /**
     * @var Route[]|null
     */
    private ?array $static = null;

    /**
     * @var Route[]|null
     */
    private ?array $dynamic = null;

    private const PATH_SEPARATOR = '/';

    /**
     * Sorts routes according to their type and computed weight.
     *
     * Routes and grouped in two groups: static routes and dynamic routes. The difference between
     * static and dynamic routes is that dynamic routes capture parameters from the path and thus
     * require a regex to compute the matches, whereas static routes only require is simple string
     * comparison.
     *
     * Dynamic routes are ordered according to their weight, which is computed from the number
     * of static parts before the first capture. The more static parts, the lighter the route is.
     *
     * @return array{0: Route[], 1: Route[]} An array with the static routes and dynamic routes.
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
                $static[] = $route;
            } else {
                $dynamic[] = $route;
                $weights[spl_object_id($route)] = substr_count($pattern->interleaved[0], self::PATH_SEPARATOR); // @phpstan-ignore-line
            }
        }

        uasort(
            $dynamic,
            // it's weight, not priority, the comparison needs to be reversed
            fn(Route $a, Route $b): int => $weights[spl_object_id($b)] <=> $weights[spl_object_id($a)]
        );

        return [ $this->static = $static, $this->dynamic = $dynamic ];
    }
}
