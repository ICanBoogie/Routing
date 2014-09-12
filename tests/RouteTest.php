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
	public function testGetPatternInstance()
	{
		$s = '/news/:year-:month-:slug.:format';
		$r = new Route($s, array());

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
}