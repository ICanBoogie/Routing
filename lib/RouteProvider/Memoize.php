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
use Traversable;

/**
 * Speed up route resolution for predicate {@link ById}, {@link ByAction}, and {@link ByUri}.
 */
final class Memoize implements RouteProvider
{
    public function __construct(
        private readonly RouteProvider $inner_provider
    ) {
    }

    public function getIterator(): Traversable
    {
        return $this->inner_provider->getIterator();
    }

    private RouteProvider $by_id;
    private RouteProvider $by_action;
    private RouteProvider $by_id_or_action;
    private RouteProvider $by_uri;

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ById) {
            return ($this->by_id ??= new MemoizeById($this->inner_provider))
                ->route_for_predicate($predicate);
        }

        if ($predicate instanceof ByAction) {
            return ($this->by_action ??= new MemoizeByAction($this->inner_provider))
                ->route_for_predicate($predicate);
        }

        if ($predicate instanceof ByIdOrAction) {
            return ($this->by_id_or_action ??= new MemoizeByIdOrAction($this->inner_provider))
                ->route_for_predicate($predicate);
        }

        if ($predicate instanceof ByUri) {
            return ($this->by_uri ??= new MemoizeByUri($this->inner_provider))
                ->route_for_predicate($predicate);
        }

        return $this->inner_provider
            ->route_for_predicate($predicate);
    }
}
