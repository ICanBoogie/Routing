<?php

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;

/**
 * A predicate that matches a route against an action.
 */
final readonly class ByAction
{
    public function __construct(
        public string $action
    ) {
    }

    public function __invoke(Route $route): bool
    {
        return $route->action === $this->action;
    }
}
