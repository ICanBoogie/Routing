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

/**
 * A route provider that supports mutations.
 */
interface MutableRouteProvider extends RouteProvider
{
    /**
     * Add routes to the provider.
     */
    public function add_routes(Route ...$route): void;
}
