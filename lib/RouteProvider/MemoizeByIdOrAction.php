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

use ArrayIterator;
use ICanBoogie\Routing\IterableRouteProvider;
use ICanBoogie\Routing\Route;
use Traversable;

use function array_values;
use function iterator_to_array;

/**
 * Speed up route resolution for predicate {@link ById}.
 */
final class MemoizeByIdOrAction implements IterableRouteProvider
{
    public function __construct(
        private readonly IterableRouteProvider $inner_provider
    ) {
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByIdOrAction) {
            return $this->route_for_id_or_action($predicate->id_or_action);
        }

        return $this->inner_provider->route_for_predicate($predicate);
    }

    /**
     * @var Route[]
     */
    private array $routes;

    public function getIterator(): Traversable
    {
        return new ArrayIterator(
            $this->routes ??= array_values(iterator_to_array($this->inner_provider->getIterator()))
        );
    }

    private IterableRouteProvider $by_id;
    private IterableRouteProvider $by_action;

    private function route_for_id_or_action(string $id_or_action): ?Route
    {
        return ($this->by_id ??= new MemoizeById($this->inner_provider))
                ->route_for_predicate(new ById($id_or_action))
            ?? ($this->by_action ??= new MemoizeByAction($this->inner_provider))
                ->route_for_predicate(new ByAction($id_or_action));
    }
}
