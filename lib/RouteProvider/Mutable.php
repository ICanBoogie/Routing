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
use ICanBoogie\Routing\MutableRouteProvider;
use ICanBoogie\Routing\Route;
use Traversable;

/**
 * A mutable route provider.
 */
final class Mutable implements IterableRouteProvider, MutableRouteProvider
{
    /**
     * @param array{ 'routes': Route[] } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        $instance = new self();
        $instance->routes = $an_array['routes'];

        return $instance;
    }

    /**
     * @var Route[]
     */
    private array $routes = [];

    public function route_for_predicate(callable $predicate): ?Route
    {
        foreach ($this->routes as $route) {
            if ($predicate($route)) {
                return $route;
            }
        }

        return null;
    }

    public function add_routes(Route ...$routes): void
    {
        foreach ($routes as $route) {
            $this->routes[] = $route;
        }
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->routes);
    }
}
