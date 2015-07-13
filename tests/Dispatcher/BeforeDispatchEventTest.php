<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\EventReflection;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Routing\Route;

class BeforeDispatchEventTest extends \PHPUnit_Framework_TestCase
{
	private $dispatcher;
	private $route;

	public function setUp()
	{
		$this->dispatcher = $this
			->getMockBuilder(Dispatcher::class)
			->disableOriginalConstructor()
			->getMock();

		$this->route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->getMock();

	}

	/**
	 * @expectedException \PHPUnit_Framework_Error
	 */
	public function test_invalid_response_type()
	{
		/* @var $dispatcher Dispatcher */
		/* @var $route Route */

		$dispatcher = $this->dispatcher;
		$route = $this->route;
		$request = Request::from('/');

		EventReflection::from(BeforeDispatchEvent::class)->with([

			'target' => $dispatcher,
			'route' => $route,
			'request' => $request,
			'response' => &$dispatcher

		]);
	}

	public function test_response_reference()
	{
		/* @var $dispatcher Dispatcher */
		/* @var $route Route */

		$dispatcher = $this->dispatcher;
		$route = $this->route;
		$request = Request::from('/');
		$response = null;
		$expected_response = new Response;

		/* @var $event BeforeDispatchEvent */

		$event = EventReflection::from(BeforeDispatchEvent::class)->with([

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
