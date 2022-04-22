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

use ICanBoogie\Routing\Exception\RouteNotFound;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;

use function is_string;

class UrlGenerator
{
    public function __construct(
        private readonly RouteProvider $routes
    ) {
    }

    /**
     * @phpstan-param string|(callable(Route): bool) $predicate_or_id_or_action
     *
     * @param array<string, mixed>|object|null $params
     *     Parameters that reference placeholders in the route pattern.
     */
    public function generate_url(string|callable $predicate_or_id_or_action, array|object|null $params = null): string
    {
        $predicate = is_string($predicate_or_id_or_action)
            ? new ByIdOrAction($predicate_or_id_or_action)
            : $predicate_or_id_or_action;

        $route = $this->routes->route_for_predicate($predicate)
            ?? throw new RouteNotFound(predicate: $predicate);

        return $route->pattern->format($params);
    }
}
