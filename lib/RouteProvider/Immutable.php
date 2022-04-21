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
use ICanBoogie\Routing\RouteProvider;
use IteratorAggregate;
use Traversable;

/**
 * An immutable route provider.
 *
 * @implements IteratorAggregate<int, Route>
 *     The key has no meaning.
 */
final class Immutable implements RouteProvider, IteratorAggregate
{
    private Mutable $mutable;

    /**
     * @param iterable<Route> $routes
     */
    public function __construct(iterable $routes)
    {
        $mutable = new Mutable();
        $mutable->add_routes(...$routes);

        $this->mutable = $mutable;
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        return $this->mutable->route_for_predicate($predicate);
    }

    public function getIterator(): Traversable
    {
        return $this->mutable->getIterator();
    }
}
