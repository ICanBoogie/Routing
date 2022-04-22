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

use ICanBoogie\Routing\Route;

/**
 * A predicate that matches a route against an identifier or an action.
 */
final class ByIdOrAction
{
    public function __construct(
        public readonly string $id_or_action
    ) {
    }

    public function __invoke(Route $route): bool
    {
        return $route->id === $this->id_or_action
            || $route->action === $this->id_or_action;
    }
}
