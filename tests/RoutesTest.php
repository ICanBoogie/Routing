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
	public function test_anonymous_routes()
	{
		$routes = new Routes([

			[ 'controller' => uniqid(), 'pattern' => uniqid() ],
			[ 'controller' => uniqid(), 'pattern' => uniqid() ]

		]);

		$this->assertFalse(isset($routes[0]));
		$this->assertFalse(isset($routes[1]));
	}

	/**
	 * @expectedException \ICanBoogie\Prototype\MethodNotDefined
	 */
	public function test_should_throw_exception_on_invalid_http_method()
	{
		$routes = new Routes;
		$routes->invalid_http_method([ 'controller' => uniqid(), 'pattern'=> uniqid() ]);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\PatternNotDefined
	 */
	public function test_pattern_not_defined()
	{
		new Routes([

			'home' => [

				'controller' => 'dummy'

			]

		]);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\ControllerNotDefined
	 */
	public function test_controller_not_defined()
	{
		new Routes([

			'home' => [

				'pattern' => '/'

			]

		]);
	}

	public function test_controller_not_defined_but_location()
	{
		new Routes([

			'home' => [

				'pattern' => '/',
				'location' => '/go/to/madonna'

			]

		]);
	}

	public function test_define_route()
	{
		$routes = new Routes;
		$dispatcher = new Dispatcher($routes);
		$routes->any('/', function(Request $request) {

			$this->assertInstanceOf('ICanBoogie\HTTP\Request', $request);

			return "Hello world";

		});

		$route = $routes->find('/');
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertStringStartsWith('anonymous_', $route->id);

		$response = $dispatcher(Request::from('/'));
		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertEquals("Hello world", $response->body);
	}

	public function test_offsetGet()
	{
		$one_pattern = '/' . uniqid();
		$one_controller = function() {};

		$routes = new Routes([

			'one' => [

				'pattern' => $one_pattern,
				'controller' => $one_controller

			]

		]);

		$route = $routes['one'];
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertInstanceOf('ICanBoogie\Routing\Pattern', $route->pattern);
		$this->assertSame($one_pattern, (string) $route->pattern);
		$this->assertSame($one_controller, $route->controller);
	}

	public function test_route_class()
	{
		$one_pattern = '/' . uniqid();
		$one_controller = function() {};
		$one_class = __CLASS__ . '\MyRouteClass';

		$routes = new Routes([

			'one' => [

				'pattern' => $one_pattern,
				'controller' => $one_controller,
				'class' => $one_class

			]

		]);

		$one = $routes['one'];
		$this->assertInstanceOf($one_class, $one);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\RouteNotDefined
	 */
	public function test_offsetGet_undefined()
	{
		$routes = new Routes;
		$routes[uniqid()];
	}

	public function test_iterator()
	{
		$routes = new Routes;
		$routes->get('/' . uniqid(), function() {}, [

			'as' => 'one'

		]);

		$routes['two'] = [

			'pattern' => '/' . uniqid(),
			'controller' => function() {}

		];

		$routes['three'] = [

			'pattern' => '/' . uniqid(),
			'controller' => function() {}

		];

		unset($routes['two']);

		$names = [];

		foreach ($routes as $id => $definition)
		{
			$names[] = $id;
			$this->assertInternalType('array', $definition);
		}

		$this->assertEquals([ 'one', 'three' ], $names);
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

		$route = $routes->find('//articles', $captured);
		$this->assertEmpty($route);
	}

	/**
	 * 'api:articles:activate' should win over 'api:nodes:activate' because more static parts
	 * are defined before the first capture.
	 */
	public function test_weigth()
	{
		$routes = new Routes([

			'api:nodes:activate' => [

				'pattern' => '/api/:constructor/:id/active',
				'controller' => 'dummy',
				'via' => 'PUT'

			],

			'api:articles:activate' => [

				'pattern' => '/api/articles/:id/active',
				'controller' => 'dummy',
				'via' => 'PUT'

			]

		]);

		$route = $routes->find('/api/articles/123/active', $captured, Request::METHOD_PUT);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('api:articles:activate', $route->id);
	}

	public function test_nameless_capture()
	{
		$routes = new Routes([

			'admin:articles/edit' => [

				'pattern' => '/admin/articles/<\d+>/edit',
				'controller' => 'dummy'

			]

		]);

		$route = $routes->find('/admin/articles/123/edit', $captured);
		$this->assertInstanceOf('ICanBoogie\Routing\Route', $route);
		$this->assertEquals('admin:articles/edit', $route->id);
	}

	/**
	 * @dataProvider provide_test_add_with_method
	 */
	public function test_add_with_method($method, $pattern, $controller, $options, $expected)
	{
		$routes = new Routes;
		$routes->$method($pattern, $controller, $options);
		$route = $routes->find('/');

		foreach ($expected as $property => $value)
		{
			$this->assertEquals($route->$property, $value);
		}
	}

	public function provide_test_add_with_method()
	{
		$to = 'My\Dummy\Controller';

		return [

			[ 'any', '/', $to , [], [

				'controller' => $to,
				'via' => 'ANY'

			] ],

			[ 'connect', '/', $to , [], [

				'controller' => $to,
				'via' => 'CONNECT'

			] ],

			[ 'delete', '/', $to , [], [

				'controller' => $to,
				'via' => 'DELETE'

			] ],

			[ 'get', '/', $to , [], [

				'controller' => $to,
				'via' => 'GET'

			] ],

			[ 'head', '/', $to , [], [

				'controller' => $to,
				'via' => 'HEAD'

			] ],

			[ 'options', '/', $to , [], [

				'controller' => $to,
				'via' => 'OPTIONS'

			] ],

			[ 'post', '/', $to , [], [

				'controller' => $to,
				'via' => 'POST'

			] ],

			[ 'put', '/', $to , [], [

				'controller' => $to,
				'via' => 'PUT'

			] ],

			[ 'patch', '/', $to , [], [

				'controller' => $to,
				'via' => 'PATCH'

			] ],

			[ 'trace', '/', $to , [], [

				'controller' => $to,
				'via' => 'TRACE'

			] ],

			[ 'get', '/', $to, [

				'controller' => 'INVALID OVERRIDE'

			], [

				'controller' => $to,
				'via' => 'GET'

			] ],

			[ 'get', '/', $to, [

				'as' => 'article:show'

			], [

				'controller' => $to,
				'id' => 'article:show',
				'via' => 'GET'

			] ],

			[ 'any', '/', $to, [

				'via' => [ 'GET', 'POST' ]

			], [

				'controller' => $to,
				'via' => [ 'GET', 'POST' ]

			] ],

		];
	}
}

namespace ICanBoogie\Routing\RoutesTest;

use ICanBoogie\Routing\Route;

class MyRouteClass extends Route
{

}
