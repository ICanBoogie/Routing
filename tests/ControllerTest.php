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

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerTest\App;
use ICanBoogie\Routing\ControllerTest\ForwardToTestController;
use ICanBoogie\Routing\ControllerTest\MySampleController;

class ControllerTest extends \PHPUnit_Framework_TestCase
{
	public function test_should_get_name()
	{
		$controller = new MySampleController;
		$this->assertEquals('my_sample', $controller->name);
	}

	public function test_should_not_get_name()
	{
		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$this->assertNull($controller->name);
	}

	public function test_lazy_get_response()
	{
		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $controller Controller */

		$response = $controller->response;

		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertSame($response, $controller->response);
	}

	public function test_invoke_should_return_response_from_action()
	{
		$request = Request::from('/');

		$response = new Response;

		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
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
			->getMockBuilder('ICanBoogie\Routing\Controller')
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
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->setMethods([ 'action' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('action')
			->willReturn($body);

		/* @var $controller Controller */

		$response = $controller->response;
		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
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

					$this->assertInstanceOf('ICanBoogie\HTTP\Request', $request);
					$this->assertEquals(2014, $year);
					$this->assertEquals(12, $month);
					$this->assertEquals("my-awesome-post", $slug);

					return 'HERE';

				}
			]
		]);

		$dispatcher = new Dispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$response = $dispatcher($request);
		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertTrue($response->status->is_successful);
		$this->assertEquals('HERE', $response->body);
	}

	public function test_generic_controller()
	{
		$routes = new RouteCollection([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => 'ICanBoogie\Routing\ControllerTest\MySampleController'
			]
		]);

		$dispatcher = new Dispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$request->test = $this;
		$response = $dispatcher($request);
		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertTrue($response->status->is_successful);
		$this->assertEquals('HERE', $response->body);
	}

	public function test_last_chance_get_application_get_value()
	{
		$expected = uniqid();

		$app = new App($expected);

		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->setMethods([ 'get_app' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->exactly(2))
			->method('get_app')
			->willReturn($app);

		$this->assertSame($app, $controller->app);
		$this->assertSame($expected, $controller->value);
	}

	public function test_last_chance_get_application_get_undefined()
	{
		$app = new App(uniqid());
		$property = 'undefined' . uniqid();

		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->setMethods([ 'get_app' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->exactly(2))
			->method('get_app')
			->willReturn($app);

		$this->assertSame($app, $controller->app);

		try
		{
			$controller->$property;

			$this->fail('Expected PropertyNotDefined');
		}
		catch (\Exception $e)
		{
			$this->assertInstanceOf('ICanBoogie\PropertyNotDefined', $e);

			$message = $e->getMessage();

			$this->assertContains($property, $message);
			$this->assertContains(get_class($controller), $message);
		}
	}

	public function test_redirect_to_path()
	{
		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$url = '/path/to/' . uniqid();

		/* @var $controller \ICanBoogie\Routing\Controller */
		/* @var $response \ICanBoogie\HTTP\RedirectResponse */

		$response = $controller->redirect($url);

		$this->assertInstanceOf('ICanBoogie\HTTP\RedirectResponse', $response);
		$this->assertSame($url, $response->location);
		$this->assertSame(302, $response->status->code);
	}

	public function test_redirect_to_route()
	{
		$url = '/path/to/' . uniqid();

		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$route = $this
			->getMockBuilder('ICanBoogie\Routing\Route')
			->disableOriginalConstructor()
			->setMethods([ 'get_url' ])
			->getMock();
		$route
			->expects($this->once())
			->method('get_url')
			->willReturn($url);

		/* @var $controller \ICanBoogie\Routing\Controller */
		/* @var $response \ICanBoogie\HTTP\RedirectResponse */

		$response = $controller->redirect($route);

		$this->assertInstanceOf('ICanBoogie\HTTP\RedirectResponse', $response);
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
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		/* @var $controller \ICanBoogie\Routing\Controller */

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
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->getMock();

		$route = new Route($routes, '/articles/<nid:\d+>/edit', [

			'controller' => function(Request $request) use ($original_request, $response) {

				$this->assertNotSame($original_request, $request);
				$this->assertEquals(123, $request['nid']);

				return $response;

			}

		]);

		/* @var $response \ICanBoogie\HTTP\RedirectResponse */
		/* @var $controller \ICanBoogie\Routing\Controller */

		$controller($original_request); // only to set private `request` property

		$this->assertSame($response, $controller->forward_to($route));
	}
}
