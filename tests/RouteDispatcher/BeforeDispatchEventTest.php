<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\RouteDispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\Route;

class BeforeDispatchEventTest extends \PHPUnit\Framework\TestCase
{
	private $dispatcher;
	private $route;

	public function setUp()
	{
		$this->dispatcher = $this
			->getMockBuilder(RouteDispatcher::class)
			->disableOriginalConstructor()
			->getMock();

		$this->route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->getMock();

	}

	public function test_invalid_response_type()
	{
		/* @var $dispatcher RouteDispatcher */
		/* @var $route Route */

		$dispatcher = $this->dispatcher;
		$route = $this->route;
		$request = Request::from('/');

		$this->expectException(\TypeError::class);

		BeforeDispatchEvent::from([

			'target' => $dispatcher,
			'route' => $route,
			'request' => $request,
			'response' => &$dispatcher

		]);
	}

	public function test_response_reference()
	{
		/* @var $dispatcher RouteDispatcher */
		/* @var $route Route */

		$dispatcher = $this->dispatcher;
		$route = $this->route;
		$request = Request::from('/');
		$response = null;
		$expected_response = new Response;

		/* @var $event BeforeDispatchEvent */

		$event = BeforeDispatchEvent::from([

			'target' => $dispatcher,
			'route' => $route,
			'request' => $request,
			'response' => &$response

		]);

		$this->assertSame($route, $event->route);
		$this->assertSame($request, $event->request);
		$this->assertNull($event->response);
		$event->response = $expected_response;
		$this->assertSame($expected_response, $event->response);
	}
}
