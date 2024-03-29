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

use IteratorAggregate;

/**
 * @extends IteratorAggregate<Route>
 *     The key has no meaning.
 */
interface RouteProvider extends IteratorAggregate
{
    /**
     * Provides the route matching the specified predicate.
     *
     * The following predicates are builtin:
     *
     * - {@link RouteProvider\ByAction}
     * - {@link RouteProvider\ById}
     * - {@link RouteProvider\ByUri}
     *
     * **Note:** Providers might optimize predicate matching and might skip the callable.
     *
     * @param (callable(Route): bool) $predicate
     */
    public function route_for_predicate(callable $predicate): ?Route;
}
