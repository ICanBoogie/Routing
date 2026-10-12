<?php

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

/**
 * A chain of route providers.
 *
 * Use a {@see Chain} with a {@see Mutable} to add a bit of flexibility to a {@see RouteProvider}.
 * The first in the chain wins.
 */
final readonly class Chain implements RouteProvider
{
    /**
     * @param iterable<RouteProvider> $route_providers
     */
    public function __construct(
        public iterable $route_providers,
    ) {
    }

    /**
     * @return Traversable<Route>
     */
    public function getIterator(): Traversable
    {
        foreach ($this->route_providers as $route_provider) {
            yield from $route_provider->getIterator();
        }
    }

    /**
     * @inheritDoc
     */
    public function route_for_predicate(callable $predicate): ?Route
    {
        foreach ($this->route_providers as $route_provider) {
            $route = $route_provider->route_for_predicate($predicate);

            if ($route) {
                return $route;
            }
        }

        return null;
    }
}
