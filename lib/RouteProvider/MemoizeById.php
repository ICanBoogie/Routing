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

use function array_values;
use function iterator_to_array;

/**
 * Speed up route resolution for predicate {@link ById}.
 */
final class MemoizeById implements RouteProvider
{
    public function __construct(
        private readonly RouteProvider $inner_provider
    ) {
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ById) {
            return $this->route_for_id($predicate->id);
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

    /**
     * @var array<string, Route>
     */
    private array $routes_by_id;

    private function route_for_id(string $id): ?Route
    {
        $this->routes_by_id ??= $this->collect_routes_by_id();

        return $this->routes_by_id[$id] ?? null;
    }

    /**
     * @return array<string, Route>
     *     Where _key_ is a route identifier.
     */
    private function collect_routes_by_id(): array
    {
        $routes = [];

        foreach ($this as $route) {
            if (!$route->id) {
                continue;
            }

            $routes[$route->id] = $route;
        }

        return $routes;
    }
}
