<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ArrayIterator;
use Countable;
use ICanBoogie\Routing\RouteMaker\Options;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\ByUri;
use IteratorAggregate;
use Traversable;

use function array_diff_key;
use function count;
use function ICanBoogie\stable_sort;
use function substr_count;

/**
 * A route collection.
 *
 * @implements IteratorAggregate<mixed, Route>
 */
final class RouteCollection implements IteratorAggregate, Countable, MutableRouteProvider
{
    /**
     * @var array<string|int, Route>
     *     Where _key_ is a route identifier or an offset and _value_ is a Route.
     */
    private array $routes = [];

    /**
     * @param iterable<Route> $routes
     */
    public function __construct(iterable $routes = [])
    {
        $this->add_routes(...$routes);
    }

    public function add_routes(Route ...$route): self
    {
        foreach ($route as $r) {
            $id = $r->id;

            if ($id) {
                $this->routes[$id] = $r;
            } else {
                $this->routes[] = $r;
            }
        }

        $this->revoke_cache();

        return $this;
    }

    /**
     * Adds resource routes.
     *
     * **Note:** The respond definitions for the resource are created by
     * {@link RouteMaker::resource}. Both methods accept the same arguments.
     *
     * @see RouteMaker::resource
     */
    public function resource(string $name, Options $options = null): self
    {
        $this->add_routes(...RouteMaker::resource($name, $options));

        return $this;
    }

    /**
     * @return Traversable<string|int, Route>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->routes));
    }

    /**
     * Returns the number of routes in the collection.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ById) {
            return $this->routes[$predicate->id] ?? null;
        }

        if ($predicate instanceof ByUri) {
            return $this->route_for_predicate_by_uri($predicate);
        }

        foreach ($this->routes as $route) {
            if ($predicate($route)) {
                return $route;
            }
        }

        return null;
    }

    private function route_for_predicate_by_uri(ByUri $predicate): ?Route
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

    /**
     * Revokes the cache used by the {@link sort_routes} method.
     */
    private function revoke_cache(): void
    {
        $this->static = null;
        $this->dynamic = null;
    }

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

        foreach ($this->routes as $route) {
            $pattern = $route->pattern;

            if (!count($pattern->params)) {
                $static[] = $route;
            } else {
                $dynamic[] = $route;
                $weights[] = substr_count($pattern->interleaved[0], self::PATH_SEPARATOR); // @phpstan-ignore-line
            }
        }

        stable_sort($dynamic, fn($v, $k) => -$weights[$k]);

        return [ $this->static = $static, $this->dynamic = $dynamic ];
    }

    /**
     * Creates a collection with filtered routes.
     *
     * @param callable(Route):bool $filter
     */
    public function filter(callable $filter): self
    {
        $routes = [];

        foreach ($this->routes as $route) {
            if (!$filter($route)) {
                continue;
            }

            $routes[] = $route;
        }

        return new self($routes);
    }
}
