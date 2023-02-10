<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

/**
 * A helper to spy on route providers.
 *
 * Mostly used to spy on memoize providers.
 */
final class SpyRouteProvider implements RouteProvider
{
    public int $times_iterator = 0;
    public int $times_route_for_predicate = 0;

    public function __construct(
        private readonly RouteProvider $inner_provider
    ) {
    }

    public function getIterator(): Traversable
    {
        $this->times_iterator++;

        return $this->inner_provider->getIterator();
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        $this->times_route_for_predicate++;

        return $this->inner_provider->route_for_predicate($predicate);
    }
}
