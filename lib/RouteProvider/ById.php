<?php

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;

/**
 * A predicate that matches a route against an identifier.
 */
final readonly class ById
{
    public function __construct(
        public string $id
    ) {
    }

    public function __invoke(Route $route): bool
    {
        return $route->id === $this->id;
    }
}
