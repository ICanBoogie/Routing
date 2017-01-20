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

use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Request;

class RouteTest extends \PHPUnit\Framework\TestCase
{
	private $routes;

	protected function setUp()
	{
		$this->routes = $this
			->getMockBuilder(RouteCollection::class)
			->disableOriginalConstructor()
			->getMock();
	}

	public function testGetPatternInstance()
	{
		$s = '/news/:year-:month-:slug.:format';
		$r = new Route($s, []);

		$this->assertInstanceOf(Pattern::class, $r->pattern);
	}

	public function test_closure_controller()
	{
		$params = [ 'one' => uniqid(), 'two' => uniqid() ];
		$request = Request::from([

			Request::OPTION_URI => '/',
			Request::OPTION_PATH_PARAMS => $params,

		]);

		$test = $this;

		$routes = new RouteCollection;
		$routes->get('/', function(...$args) use ($request, $params, $test) {

			/* @var $this ClosureController */

			$test->assertInstanceOf(ClosureController::class, $this);
			$test->assertSame($request, $this->request);
			$test->assertSame(array_values($params), $args);

			return 'madonna';

		});

		$dispatcher = new RouteDispatcher($routes);
		$response = $dispatcher($request);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertEquals('madonna', $response->body);
	}

	public function test_format()
	{
		$route = new Route('/news/:year-:month-:slug.html', []);

		$formatted_route = $route->format([

			'year' => '2014',
			'month' => '06',
			'slug' => 'madonna-queen-of-pop'

		]);

		$expected_url = '/news/2014-06-madonna-queen-of-pop.html';

		$this->assertInstanceOf(FormattedRoute::class, $formatted_route);
		$this->assertEquals($expected_url, (string) $formatted_route);
		$this->assertEquals($expected_url, $formatted_route->url);
		$this->assertEquals("http://icanboogie.org{$expected_url}", $formatted_route->absolute_url);
	}

	public function test_get_url()
	{
		$expected = "/my-awesome-url.html";
		$route = new Route($expected, []);
		$this->assertEquals($expected, $route->url);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\PatternRequiresValues
	 */
	public function test_get_url_requiring_values()
	{
		$expected = "/:year-:month.html";
		$route = new Route($expected, []);
		$this->assertEquals($expected, $route->url);
	}

	public function test_get_absolute_url()
	{
		$expected = "/my-awesome-url.html";
		$route = new Route($expected, []);
		$this->assertEquals("http://icanboogie.org" . $expected, $route->absolute_url);
	}

	/**
	 * @dataProvider provide_invalid_construct_properties
	 * @expectedException \InvalidArgumentException
	 *
	 * @param $properties
	 */
	public function test_should_throw_exception_on_invalid_construct_property($properties)
	{
		new Route('/', $properties);
	}

	public function provide_invalid_construct_properties()
	{
		return [

			[ [ 'formatting_value' => uniqid() ] ],
			[ [ 'url' => uniqid() ] ],
			[ [ 'absolute_url' => uniqid() ] ]

		];
	}

	public function test_with()
	{
		$year = uniqid();
		$month = uniqid();
		$formatting_value = [ 'year' => $year, 'month' => $month ];
		$r1 = new Route('/:year-:month.html', []);
		$this->assertNull($r1->formatting_value);
		$this->assertFalse($r1->has_formatting_value);

		$r2 = $r1->assign($formatting_value);
		$this->assertInstanceOf(Route::class, $r2);
		$this->assertNotSame($r1, $r2);
		$this->assertSame($formatting_value, $r2->formatting_value);
		$this->assertTrue($r2->has_formatting_value);

		$expected_url = "/{$year}-{$month}.html";

		$this->assertSame($expected_url, $r2->url);
		$this->assertSame($expected_url, (string) $r2);
	}

	public function test_should_reset_formatting_value_on_clone()
	{
		$formatting_value = [ 'a' => uniqid() ];
		$route = (new Route('/', []))->assign($formatting_value);

		$route2 = clone $route;
		$this->assertNull($route2->formatting_value);
		$this->assertFalse($route2->has_formatting_value);
	}
}
