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

use ICanBoogie\Events;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher\BeforeDispatchEvent;
use ICanBoogie\Routing\Route\RescueEvent;

class DispatcherTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Events
	 */
	private $events;

	private $events_callable;

	public function setUp()
	{
		$this->events = $events = new Events;
		$this->events_callable = Events::patch('get', function() use ($events) {

			return $events;

		});
	}

	public function tearDown()
	{
		Events::patch('get', $this->events_callable);
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
		$dispatcher = new Dispatcher($routes);

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
			->willReturnCallback(function() use (&$route) {

				return $route;

			});

		/* @var $routes RouteCollection */

		$route = new Route($routes, '/', [

			'location' => $location

		]);

		$request = Request::from('/');
		$dispatcher = new Dispatcher($routes);
		$response = $dispatcher($request);

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertEquals($location, $response->location);
	}

	public function test_dispatch()
	{
		$called_before_dispatch = false;

		$events = $this->events;
		$events->attach(function(BeforeDispatchEvent $event, Dispatcher $target) use (&$request, &$expected_response, &$called_before_dispatch) {

			$called_before_dispatch = true;

			$this->assertSame($request, $event->request);
			$this->assertNull($event->response);

		});

		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->setMethods([ 'find' ])
			->getMock();
		$routes
			->expects($this->once())
			->method('find')
			->willReturnCallback(function($path, &$captured) use (&$route) {

				$captured = [];

				return $route;

			});

		$expected_response = new Response;

		$dispatcher = $this
			->getMockBuilder(Dispatcher::class)
			->setConstructorArgs([ $routes ])
			->setMethods([ 'respond' ])
			->getMock();
		$dispatcher
			->expects($this->once())
			->method('respond')
			->willReturn($expected_response);

		/* @var $routes RouteCollection */

		$route = new Route($routes, '/', [

			'pattern' => '/',
			'controller' => function() {}

		]);

		/* @var $dispatcher Dispatcher */

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
			->getMockBuilder(Dispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher Dispatcher */

		try
		{
			$dispatcher->rescue($exception, $request);
		}
		catch (\Exception $e)
		{
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
			->getMockBuilder(Dispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher Dispatcher */

		$this->events->once(function(RescueEvent $event, Route $target) use ($route, $new_exception) {

			$this->assertSame($route, $target);
			$event->exception = $new_exception;

		});

		try
		{
			$dispatcher->rescue($exception, $request);
		}
		catch (\Exception $e)
		{
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
			->getMockBuilder(Dispatcher::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		/* @var $dispatcher Dispatcher */

		$this->events->once(function(RescueEvent $event, Route $target) use ($route, $new_response) {

			$this->assertSame($route, $target);
			$event->response = $new_response;

		});

		$response = $dispatcher->rescue($exception, $request);
		$this->assertSame($new_response, $response);
	}
}
