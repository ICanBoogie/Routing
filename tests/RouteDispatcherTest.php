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

use ICanBoogie\Routing\Responder\RouteResponder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class RouteDispatcherTest extends TestCase
{
	use ProphecyTrait;

	private ObjectProphecy|RouteProvider $routes;
	private ObjectProphecy|ResponderProvider $responders;

	protected function setUp(): void
	{
		parent::setUp();

		$this->markTestSkipped();

		$this->routes = $this->prophesize(RouteProvider::class);
		$this->responders = $this->prophesize(ResponderProvider::class);
	}

	public function test_get_routes()
	{
		$dispatcher = new RouteDispatcher(
			new Rescue(
				new Alter(
					new RouteResponder(
						$this->routes->reveal(),
						$this->responders->reveal(),
					)
				)
			)
		);
	}
}
