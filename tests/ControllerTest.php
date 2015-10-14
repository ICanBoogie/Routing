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

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerTest\MySampleController;

class ControllerTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$this->events = $events = new EventCollection;

		EventCollectionProvider::using(function() use ($events) {

			return $events;

		});
	}

	public function test_should_get_name()
	{
		$controller = new MySampleController;
		$this->assertEquals('my_sample', $controller->name);
	}

	public function test_should_not_get_name()
	{
		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $controller Controller */

		$this->assertNull($controller->name);
	}

	public function test_lazy_get_response()
	{
		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $controller Controller */

		$response = $controller->response;

		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame($response, $controller->response);
	}

	public function test_invoke_should_return_response_from_action()
	{
		$request = Request::from('/');

		$response = new Response;

		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->setMethods([ 'action' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('action')
			->willReturn($response);

		/* @var $controller Controller */

		$this->assertSame($response, $controller($request));
	}

	public function test_invoke_should_return_string()
	{
		$request = Request::from('/');

		$body = "some string" . uniqid();

		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->setMethods([ 'action' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('action')
			->willReturn($body);

		/* @var $controller Controller */

		$response = $controller($request);
		$this->assertSame($body, $response);
	}

	public function test_invoke_should_return_string_in_response()
	{
		$request = Request::from('/');

		$body = "some string" . uniqid();

		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->setMethods([ 'action' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('action')
			->willReturn($body);

		/* @var $controller Controller */

		$response = $controller->response;
		$this->assertInstanceOf(Response::class, $response);
		$response2 = $controller($request);
		$this->assertSame($response, $response2);
		$this->assertSame($body, $response2->body);
	}

	public function test_closure()
	{
		$routes = new RouteCollection([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => function(Request $request, $year, $month, $slug) {

					$this->assertInstanceOf(Request::class, $request);
					$this->assertEquals(2014, $year);
					$this->assertEquals(12, $month);
					$this->assertEquals("my-awesome-post", $slug);

					return 'HERE';

				}
			]
		]);

		$dispatcher = new RouteDispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$response = $dispatcher($request);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertTrue($response->status->is_successful);
		$this->assertEquals('HERE', $response->body);
	}

	public function test_generic_controller()
	{
		$routes = new RouteCollection([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => MySampleController::class
			]
		]);

		$dispatcher = new RouteDispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$request->test = $this;
		$response = $dispatcher($request);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertTrue($response->status->is_successful);
		$this->assertEquals('HERE', $response->body);
	}

	public function test_redirect_to_path()
	{
		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$url = '/path/to/' . uniqid();

		/* @var $controller Controller */
		/* @var $response RedirectResponse */

		$response = $controller->redirect($url);

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame($url, $response->location);
		$this->assertSame(302, $response->status->code);
	}

	public function test_redirect_to_route()
	{
		$url = '/path/to/' . uniqid();

		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->setMethods([ 'get_url' ])
			->getMock();
		$route
			->expects($this->once())
			->method('get_url')
			->willReturn($url);

		/* @var $controller Controller */
		/* @var $response RedirectResponse */

		$response = $controller->redirect($route);

		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame($url, $response->location);
		$this->assertSame(302, $response->status->code);
	}

	/**
	 * @dataProvider provide_test_forward_to_invalid
	 * @expectedException \InvalidArgumentException
	 *
	 * @param mixed $invalid
	 */
	public function test_forward_to_invalid($invalid)
	{
		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $controller Controller */

		$controller->forward_to($invalid);
	}

	public function provide_test_forward_to_invalid()
	{
		return [

			[ uniqid() ],
			[ (object) [ uniqid() => uniqid()] ],
			[ [ uniqid() => uniqid()] ]

		];
	}

	public function test_forward_to_route()
	{
		$original_request = Request::from('/articles/123/edit');
		$response = new Response;

		$controller = $this
			->getMockBuilder(Controller::class)
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $response RedirectResponse */
		/* @var $controller Controller */

		$route = new Route('/articles/<nid:\d+>/edit', [

			'controller' => function(Request $request) use ($original_request, $response) {

				$this->assertNotSame($original_request, $request);
				$this->assertEquals(123, $request['nid']);

				return $response;

			}

		]);

		$controller($original_request); // only to set private `request` property

		$this->assertSame($response, $controller->forward_to($route));
	}
}
