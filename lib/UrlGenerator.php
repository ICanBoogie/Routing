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
use ICanBoogie\Routing\RouteProvider\ById;

use function is_string;

class UrlGenerator
{
    public function __construct(
        private readonly RouteProvider $routes
    ) {
    }

    /**
     * @phpstan-param string|(callable(Route): bool) $predicate
     *
     * @param array<string, mixed>|object|null $params
     *     Parameters that reference placeholders in the route pattern.
     *
     * @return string
     */
    public function generate_url(string|callable $predicate, array|object|null $params = null): string
    {
        if (is_string($predicate)) {
            $predicate = new ById($predicate);
        }

        $route = $this->routes->route_for_predicate($predicate)
            ?? throw new RouteNotFound(predicate: $predicate);

        return $route->pattern->format($params);
    }
}
