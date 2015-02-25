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

class RouteTest extends \PHPUnit_Framework_TestCase
{
	private $routes;

	protected function setUp()
	{
		$this->routes = $this
			->getMockBuilder('ICanBoogie\Routing\Routes')
			->disableOriginalConstructor()
			->getMock();
	}

	public function test_get_routes()
	{
		$r = new Route($this->routes, '/', []);

		$this->assertSame($this->routes, $r->routes);
	}

	public function testGetPatternInstance()
	{
		$s = '/news/:year-:month-:slug.:format';
		$r = new Route($this->routes, $s, array());

		$this->assertInstanceOf('ICanBoogie\Routing\Pattern', $r->pattern);
	}

	public function testRouteCallbackResponse()
	{
		$routes = new Routes;
		$routes->get('/', function(Request $request)
		{
			return 'madonna';
		});

		$dispatcher = new Dispatcher($routes);

		$response = $dispatcher(Request::from(array('path' => '/', 'method' => 'GET')));

		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertEquals('madonna', $response->body);
	}

	public function test_format()
	{
		$route = new Route($this->routes, '/news/:year-:month-:slug.html', []);

		$formatted_route = $route->format([

			'year' => '2014',
			'month' => '06',
			'slug' => 'madonna-queen-of-pop'

		]);

		$expected_url = '/news/2014-06-madonna-queen-of-pop.html';

		$this->assertInstanceOf('ICanBoogie\Routing\FormattedRoute', $formatted_route);
		$this->assertEquals($expected_url, (string) $formatted_route);
		$this->assertEquals($expected_url, $formatted_route->url);
		$this->assertEquals("http://icanboogie.org{$expected_url}", $formatted_route->absolute_url);
	}

	public function test_get_url()
	{
		$expected = "/my-awesome-url.html";
		$route = new Route($this->routes, $expected, []);
		$this->assertEquals($expected, $route->url);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\PatternRequiresValues
	 */
	public function test_get_url_requiring_values()
	{
		$expected = "/:year-:month.html";
		$route = new Route($this->routes, $expected, []);
		$this->assertEquals($expected, $route->url);
	}

	public function test_get_absolute_url()
	{
		$expected = "/my-awesome-url.html";
		$route = new Route($this->routes, $expected, []);
		$this->assertEquals("http://icanboogie.org" . $expected, $route->absolute_url);
	}
}
