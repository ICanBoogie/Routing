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

use ICanBoogie\Routing\IterableRouteProvider;
use ICanBoogie\Routing\Route;
use ReflectionClass;
use ReflectionException;
use Traversable;

/**
 * An immutable route provider.
 */
final class Immutable implements IterableRouteProvider
{
    private Mutable $mutable;

    /**
     * @param array{ 'mutable': Mutable } $an_array
     *
     * @throws ReflectionException
     */
    public static function __set_state(array $an_array): self
    {
        /* @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->mutable = $an_array['mutable'];

        return $instance;
    }

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
