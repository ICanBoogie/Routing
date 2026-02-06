<?php

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
     * - {@see RouteProvider\ByAction}
     * - {@see RouteProvider\ById}
     * - {@see RouteProvider\ByUri}
     *
     * **Note**: Providers might optimize predicate matching and might skip the callable.
     *
     * @param (callable(Route): bool) $predicate
     */
    public function route_for_predicate(callable $predicate): ?Route;
}
