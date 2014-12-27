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

class ControllerTest extends \PHPUnit_Framework_TestCase
{
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
		$this->assertTrue($response->is_successful);
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
		$this->assertTrue($response->is_successful);
		$this->assertEquals('HERE', $response->body);
	}
}

namespace ICanBoogie\Routing\ControllerTest;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;
use ICanBoogie\Routing\HubControllerInterface;

class MySampleController extends Controller
{
	public function __invoke(Request $request)
	{
		$request->test->assertInstanceOf('ICanBoogie\HTTP\Request', $request);
		$request->test->assertEquals(1, func_num_args());
		$request->test->assertEquals("my_sample", $this->name);
		$request->test->assertInstanceOf('ICanBoogie\Routing\Route', $this->route);

		return 'HERE';
	}
}
