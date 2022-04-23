<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\UrlGenerator;

use ICanBoogie\Routing\Exception\RouteNotFound;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;

use ICanBoogie\Routing\UrlGenerator;

use function http_build_query;
use function is_string;

class UrlGeneratorWithRouteProvider implements UrlGenerator
{
    public function __construct(
        private readonly RouteProvider $routes
    ) {
    }

    public function generate_url(
        string|callable $predicate_or_id_or_action,
        array|object|null $path_params = null,
        array|object|null $query_params = null,
    ): string {
        $predicate = is_string($predicate_or_id_or_action)
            ? new ByIdOrAction($predicate_or_id_or_action)
            : $predicate_or_id_or_action;

        $route = $this->routes->route_for_predicate($predicate)
            ?? throw new RouteNotFound(predicate: $predicate);

        $url = $route->pattern->format($path_params);

        if ($query_params) {
            $url .= '?' . http_build_query($query_params);
        }

        return $url;
    }
}
