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
 * A route provider that provides an iterator.
 *
 * @extends IteratorAggregate<int, Route>
 *     The key has no meaning.
 */
interface IterableRouteProvider extends RouteProvider, IteratorAggregate
{
}
