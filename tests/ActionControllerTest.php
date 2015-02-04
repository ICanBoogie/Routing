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

class ActionControllerTest extends \PHPUnit_Framework_TestCase
{
	public function test_action()
	{
		$routes = new Routes([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => 'ICanBoogie\Routing\ActionControllerTest\A#view'
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

namespace ICanBoogie\Routing\ActionControllerTest;

use ICanBoogie\Routing\ActionController;

class A extends ActionController
{
	protected function action_view($year, $month, $slug)
	{
		$test = $this->request->test;
		$test->assertEquals('view', $this->action);
		$test->assertEquals(3, func_num_args());
		$test->assertEquals(2014, $year);
		$test->assertEquals(12, $month);
		$test->assertEquals("my-awesome-post", $slug);

		return 'HERE';
	}
}
