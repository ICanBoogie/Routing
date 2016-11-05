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

class RouteDispatcherTest extends \PHPUnit\Framework\TestCase
{
	public function test_get_routes()
	{
		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->getMock();

		$dispatcher = new RouteDispatcher($routes);
		$this->assertSame($routes, $dispatcher->routes);
	}
}
