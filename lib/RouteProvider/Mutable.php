<?php

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\Routing\MutableRouteProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

/**
 * A mutable route provider.
 */
final class Mutable implements RouteProvider, MutableRouteProvider
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
        return array_find($this->routes, fn($route) => $predicate($route));
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
