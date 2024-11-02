<?php

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;

/**
 * A predicate that matches a route against an identifier or an action.
 */
final readonly class ByIdOrAction
{
    public function __construct(
        public string $id_or_action
    ) {
    }

    public function __invoke(Route $route): bool
    {
        return $route->id === $this->id_or_action
            || $route->action === $this->id_or_action;
    }
}
