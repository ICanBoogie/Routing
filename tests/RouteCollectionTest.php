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

class RouteCollectionTest extends \PHPUnit_Framework_TestCase
{
	public function test_anonymous_routes()
	{
		$routes = new RouteCollection([

			[ RouteDefinition::CONTROLLER => uniqid(), RouteDefinition::PATTERN => uniqid() ],
			[ RouteDefinition::CONTROLLER => uniqid(), RouteDefinition::PATTERN => uniqid() ]

		]);

		$this->assertFalse(isset($routes[0]));
		$this->assertFalse(isset($routes[1]));
	}

	/**
	 * @expectedException \ICanBoogie\Prototype\MethodNotDefined
	 */
	public function test_should_throw_exception_on_invalid_http_method()
	{
		$routes = new RouteCollection;
		$m = 'invalid_http_method';
		$routes->$m([ RouteDefinition::CONTROLLER => uniqid(), RouteDefinition::PATTERN => uniqid() ]);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\PatternNotDefined
	 */
	public function test_pattern_not_defined()
	{
		new RouteCollection([

			'home' => [

				RouteDefinition::CONTROLLER => 'dummy'

			]

		]);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\ControllerNotDefined
	 */
	public function test_controller_not_defined()
	{
		new RouteCollection([

			'home' => [

				RouteDefinition::PATTERN => '/'

			]

		]);
	}

	public function test_controller_not_defined_but_location()
	{
		new RouteCollection([

			'home' => [

				RouteDefinition::PATTERN => '/',
				RouteDefinition::LOCATION => '/go/to/madonna'

			]

		]);
	}

	public function test_define_route()
	{
		$routes = new RouteCollection;
		$dispatcher = new RouteDispatcher($routes);
		$routes->any('/', function(Request $request) {

			$this->assertInstanceOf(Request::class, $request);

			return "Hello world";

		});

		$route = $routes->find('/');
		$this->assertInstanceOf(Route::class, $route);
		$this->assertStringStartsWith('anonymous_', $route->id);

		$response = $dispatcher(Request::from('/'));
		$this->assertInstanceOf(Response::class, $response);
		$this->assertEquals("Hello world", $response->body);
	}

	public function test_offsetGet()
	{
		$one_pattern = '/' . uniqid();
		$one_controller = function() {};

		$routes = new RouteCollection([

			'one' => [

				RouteDefinition::PATTERN => $one_pattern,
				RouteDefinition::CONTROLLER => $one_controller

			]

		]);

		$route = $routes['one'];
		$this->assertInstanceOf(Route::class, $route);
		$this->assertInstanceOf(Pattern::class, $route->pattern);
		$this->assertSame($one_pattern, (string) $route->pattern);
		$this->assertSame($one_controller, $route->controller);
	}

	public function test_route_class()
	{
		$one_pattern = '/' . uniqid();
		$one_controller = function() {};
		$one_class = \ICanBoogie\Routing\RouteCollectionTest\MyRouteClass::class;

		$routes = new RouteCollection([

			'one' => [

				RouteDefinition::PATTERN => $one_pattern,
				RouteDefinition::CONTROLLER => $one_controller,
				RouteDefinition::CONSTRUCTOR => $one_class

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
		$routes = new RouteCollection;
		$routes[uniqid()];
	}

	public function test_iterator()
	{
		$routes = new RouteCollection;
		$routes->get('/' . uniqid(), function() {}, [

			RouteDefinition::ID => 'one'

		]);

		$routes['two'] = [

			RouteDefinition::PATTERN => '/' . uniqid(),
			RouteDefinition::CONTROLLER => function() {}

		];

		$routes['three'] = [

			RouteDefinition::PATTERN => '/' . uniqid(),
			RouteDefinition::CONTROLLER => function() {}

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
		$routes = new RouteCollection([

			'home' => [

				RouteDefinition::PATTERN => '/',
				RouteDefinition::CONTROLLER => 'dummy'

			],

			'articles:edit' => [

				RouteDefinition::PATTERN => '/articles/new',
				RouteDefinition::CONTROLLER => 'dummy',
				RouteDefinition::VIA => Request::METHOD_GET

			],

			'articles' => [

				RouteDefinition::PATTERN => '/articles',
				RouteDefinition::CONTROLLER => 'dummy',
				RouteDefinition::VIA => [ Request::METHOD_POST, Request::METHOD_PATCH ]

			],

			'articles:delete' => [

				RouteDefinition::PATTERN => '/articles/<nid:\d+>',
				RouteDefinition::CONTROLLER => 'dummy',
				RouteDefinition::VIA => Request::METHOD_DELETE

			]

		]);

		$route = $routes->find('/');
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('home', $route->id);

		$route = $routes->find('/', $captured, Request::METHOD_PATCH);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('home', $route->id);

		$route = $routes->find('/?madonna', $captured);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('home', $route->id);
		$this->assertEquals([ '__query__' => [ 'madonna' => "" ] ], $captured);

		$route = $routes->find('/undefined');
		$this->assertEmpty($route);

		$route = $routes->find('/undefined?madonna');
		$this->assertEmpty($route);

		$route = $routes->find('/articles');
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_POST);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_PATCH);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('articles', $route->id);

		$route = $routes->find('/articles', $captured, Request::METHOD_GET);
		$this->assertEmpty($route);

		$route = $routes->find('/articles/123', $captured, Request::METHOD_DELETE);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('articles:delete', $route->id);
		$this->assertEquals([ 'nid' => 123 ], $captured);

		$route = $routes->find('/articles/123', $captured, Request::METHOD_DELETE, 'articles');
		$this->assertInstanceOf(Route::class, $route);
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
		$routes = new RouteCollection([

			'api:nodes:activate' => [

				RouteDefinition::PATTERN => '/api/:constructor/:id/active',
				RouteDefinition::CONTROLLER => 'dummy',
				RouteDefinition::VIA => 'PUT'

			],

			'api:articles:activate' => [

				RouteDefinition::PATTERN => '/api/articles/:id/active',
				RouteDefinition::CONTROLLER => 'dummy',
				RouteDefinition::VIA => 'PUT'

			]

		]);

		$route = $routes->find('/api/articles/123/active', $captured, Request::METHOD_PUT);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('api:articles:activate', $route->id);
	}

	public function test_nameless_capture()
	{
		$routes = new RouteCollection([

			'admin:articles/edit' => [

				RouteDefinition::PATTERN => '/admin/articles/<\d+>/edit',
				RouteDefinition::CONTROLLER => 'dummy'

			]

		]);

		$route = $routes->find('/admin/articles/123/edit', $captured);
		$this->assertInstanceOf(Route::class, $route);
		$this->assertEquals('admin:articles/edit', $route->id);
	}

	/**
	 * @dataProvider provide_test_add_with_method
	 *
	 * @param string $method
	 * @param string $pattern
	 * @param string $controller
	 * @param array $options
	 * @param array $expected
	 */
	public function test_add_with_method($method, $pattern, $controller, $options, $expected)
	{
		$routes = new RouteCollection;
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

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'ANY'

			] ],

			[ 'connect', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'CONNECT'

			] ],

			[ 'delete', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'DELETE'

			] ],

			[ 'get', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'GET'

			] ],

			[ 'head', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'HEAD'

			] ],

			[ 'options', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'OPTIONS'

			] ],

			[ 'post', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'POST'

			] ],

			[ 'put', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'PUT'

			] ],

			[ 'patch', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'PATCH'

			] ],

			[ 'trace', '/', $to , [], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'TRACE'

			] ],

			[ 'get', '/', $to, [

				RouteDefinition::CONTROLLER => 'INVALID OVERRIDE'

			], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => 'GET'

			] ],

			[ 'get', '/', $to, [

				RouteDefinition::ID => 'article:show'

			], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::ID => 'article:show',
				RouteDefinition::VIA => 'GET'

			] ],

			[ 'any', '/', $to, [

				RouteDefinition::VIA => [ 'GET', 'POST' ]

			], [

				RouteDefinition::CONTROLLER => $to,
				RouteDefinition::VIA => [ 'GET', 'POST' ]

			] ],

		];
	}

	public function test_routes()
	{
		$routes = new RouteCollection;
		$routes->resource('photos', 'PhotoController', [ 'only' => [ 'index', 'show' ] ]);
		$ids = [];

		foreach ($routes as $route)
		{
			$ids[] = $route['id'];
		}

		$this->assertSame([ 'photos:index', 'photos:show' ], $ids);
	}

	public function test_filter()
	{
		$routes = new RouteCollection([

			'admin:articles:index' => [

				RouteDefinition::PATTERN => '/admin/articles',
				RouteDefinition::CONTROLLER => 'dummy'

			],

			'articles:show' => [

				RouteDefinition::PATTERN => '/articles/<id:\d+>',
				RouteDefinition::CONTROLLER => 'dummy'

			]

		]);

		$filtered_routes = $routes->filter(function(array $definition, $id) {

			return strpos($id, 'admin:') === 0 && !preg_match('/:admin$/', $id);

		});

		$this->assertNotSame($routes, $filtered_routes);
		$this->assertEquals(2, count($routes));
		$this->assertEquals(1, count($filtered_routes));
		$this->assertFalse(isset($filtered_routes['articles:show']));
		$this->assertTrue(isset($filtered_routes['admin:articles:index']));
	}
}

namespace ICanBoogie\Routing\RouteCollectionTest;

use ICanBoogie\Routing\Route;

class MyRouteClass extends Route
{

}
