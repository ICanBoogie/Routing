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

class ControllerTest extends \PHPUnit_Framework_TestCase
{
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

	public function test_invoke_should_return_response_from_respond()
	{
		$request = Request::from('/');

		$response = new Response;

		$controller = $this
			->getMockBuilder('ICanBoogie\Routing\Controller')
			->disableOriginalConstructor()
			->setMethods([ 'respond' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('respond')
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
			->setMethods([ 'respond' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('respond')
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
			->setMethods([ 'respond' ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('respond')
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
		$routes = new Routes([

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
		$routes = new Routes([

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
}

namespace ICanBoogie\Routing\ControllerTest;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;
use ICanBoogie\Routing\HubControllerInterface;

class MySampleController extends Controller
{
	protected function respond(Request $request)
	{
		$request->test->assertInstanceOf('ICanBoogie\HTTP\Request', $request);
		$request->test->assertEquals(1, func_num_args());
		$request->test->assertEquals("my_sample", $this->name);
		$request->test->assertInstanceOf('ICanBoogie\Routing\Route', $this->route);

		return 'HERE';
	}
}
