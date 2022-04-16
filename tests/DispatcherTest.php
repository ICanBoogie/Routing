<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
	/**
	 * @var EventCollection
	 */
	private $events;

	protected function setUp(): void
	{
		$this->markTestIncomplete();

		$this->events = $events = new EventCollection;

		EventCollectionProvider::define(function () use ($events) {
			return $events;
		});
	}

	public function test_should_return_null_when_no_route_matches()
	{
		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->setMethods([ 'find' ])
			->getMock();
		$routes
			->expects($this->once())
			->method('find')
			->willReturn(null);

		/* @var $routes RouteCollection */

		$request = Request::from('/');
		$dispatcher = new RouteDispatcher($routes);

		$this->assertNull($dispatcher($request));
	}

	public function test_should_return_redirect_response_if_route_has_location()
	{
		$location = '/path/to/location/' . uniqid();

		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->setMethods([ 'find' ])
			->getMock();
		$routes
			->expects($this->once())
			->method('find')
			->willReturnCallback(function () use (&$route) {
				return $route;
			});

		/* @var $routes \ICanBoogie\Routing\RouteCollection */

		$route = new Route('/', [

			'location' => $location

		]);

		$request = Request::from('/');
		$dispatcher = new RouteDispatcher($routes);
		$response = $dispatcher($request);

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertEquals($location, $response->location);
	}

	public function test_dispatch()
	{
		$called_before_dispatch = false;

		$events = $this->events;
		$events->attach(
			function (Route\BeforeRespondEvent $event, RouteDispatcher $target) use (
				&$request,
				&$expected_response,
				&
				$called_before_dispatch
			) {
				$called_before_dispatch = true;

				$this->assertSame($request, $event->request);
				$this->assertNull($event->response);
			}
		);

		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->setMethods([ 'find' ])
			->getMock();
		$routes
			->expects($this->once())
			->method('find')
			->willReturnCallback(function ($path, &$captured) use (&$route) {
				$captured = [];

				return $route;
			});

		$expected_response = new Response;

		$dispatcher = $this
			->getMockBuilder(RouteDispatcher::class)
			->setConstructorArgs([ $routes ])
			->setMethods([ 'respond' ])
			->getMock();
		$dispatcher
			->expects($this->once())
			->method('respond')
			->willReturn($expected_response);

		/* @var $routes RouteCollection */

		$route = new Route('/', [

			'pattern' => '/',
			'controller' => function () {
			}

		]);

		/* @var $dispatcher RouteDispatcher */

		$request = Request::from('/');
		$response = $dispatcher($request);
		$this->assertTrue($called_before_dispatch);
		$this->assertSame($expected_response, $response);
	}

	public function test_should_rethrow_unrescued_exception()
	{
		$exception = new \Exception;
		$request = Request::from('/');
		$dispatcher = $this
			->getMockBuilder(RouteDispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher RouteDispatcher */

		try {
			$dispatcher->rescue($exception, $request);
		} catch (\Exception $e) {
			$this->assertSame($exception, $e);

			return;
		}

		$this->fail("Expected Exception");
	}

	public function test_should_throw_exception_provided_during_rescue_event()
	{
		$exception = new \Exception("OLD");
		$new_exception = new \Exception("NEW");
		$route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->getMock();
		$request = Request::from('/');
		$request->context->route = $route;
		$dispatcher = $this
			->getMockBuilder(RouteDispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher RouteDispatcher */

		$this->events->once(function (Route\RescueEvent $event, Route $target) use ($route, $new_exception) {
			$this->assertSame($route, $target);
			$event->exception = $new_exception;
		});

		try {
			$dispatcher->rescue($exception, $request);
		} catch (\Exception $e) {
			$this->assertSame($new_exception, $e);

			return;
		}

		$this->fail("Expected Exception");
	}

	public function test_should_return_new_response_provided_during_rescue_event()
	{
		$exception = new \Exception;
		$new_response = new Response;
		$route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->getMock();
		$request = Request::from('/');
		$request->context->route = $route;
		$dispatcher = $this
			->getMockBuilder(RouteDispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher RouteDispatcher */

		$this->events->once(function (Route\RescueEvent $event, Route $target) use ($route, $new_response) {
			$this->assertSame($route, $target);
			$event->response = $new_response;
		});

		$response = $dispatcher->rescue($exception, $request);
		$this->assertSame($new_response, $response);
	}
}
