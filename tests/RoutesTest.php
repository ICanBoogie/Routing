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

class RoutesTest extends \PHPUnit_Framework_TestCase
{
	public function test_define_route()
	{
		$test = $this;

		$routes = new Routes;
		$dispatcher = new Dispatcher($routes);
		$routes->any('/', function() use($test) {

			$test->assertInstanceOf('ICanBoogie\Routing\Route', $this);

			return "Hello world";

		});

		$route = $routes->find('/');
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('ANY /', $route->id);

		$response = $dispatcher(Request::from('/'));
		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertEquals("Hello world", $response->body);
	}

	public function test_find()
	{
		$routes = new Routes([

			'home' => [

				'pattern' => '/',
				'controller' => 'dummy'

			],

			'articles:edit' => [

				'pattern' => '/articles/new',
				'controller' => 'dummy',
				'via' => Request::METHOD_GET

			],

			'articles' => [

				'pattern' => '/articles',
				'controller' => 'dummy',
				'via' => [ Request::METHOD_POST, Request::METHOD_PATCH ]

			],

			'articles:delete' => [

				'pattern' => '/articles/<nid:\d+>',
				'controller' => 'dummy',
				'via' => Request::METHOD_DELETE

			]

		]);

		$route = $routes->find('/');
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('home', $route->id);

		$route = $routes->find('/', $captured, Request::METHOD_PATCH);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('home', $route->id);

		$route = $routes->find('/?madonna', $captured);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('home', $route->id);
		$this->assertEquals([ '__query__' => [ 'madonna' => "" ] ], $captured);

		$route = $routes->find('/undefined');
		$this->assertEmpty($route);

		$route = $routes->find('/undefined?madonna');
		$this->assertEmpty($route);

		$route = $routes->find('/articles');
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_POST);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_PATCH);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_GET);
		$this->assertEmpty($route);

		$route = $routes->find('/articles/123', $captured, Request::METHOD_DELETE);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('articles:delete', $route->id);
		$this->assertEquals([ 'nid' => 123 ], $captured);

		$route = $routes->find('/articles/123', $captured, Request::METHOD_DELETE, 'articles');
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('articles:delete', $route->id);
	}
}