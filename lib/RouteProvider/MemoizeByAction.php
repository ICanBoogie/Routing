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
use ICanBoogie\Routing\IterableRouteProvider;
use ICanBoogie\Routing\Route;
use Traversable;

use function array_values;
use function iterator_to_array;

/**
 * Speed up route resolution for predicate {@link ByAction}.
 */
final class MemoizeByAction implements IterableRouteProvider
{
    public function __construct(
        private readonly IterableRouteProvider $inner_provider
    ) {
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByAction) {
            return $this->route_for_action($predicate->action);
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
    private array $routes_by_action;

    private function route_for_action(string $action): ?Route
    {
        $this->routes_by_action ??= $this->collect_routes_by_action();

        return $this->routes_by_action[$action] ?? null;
    }

    /**
     * @return array<string, Route>
     *     Where _key_ is a route action.
     */
    private function collect_routes_by_action(): array
    {
        $routes = [];

        foreach ($this as $route) {
            $routes[$route->action] = $route;
        }

        return $routes;
    }
}
