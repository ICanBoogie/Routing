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
use ICanBoogie\Routing\Route\RescueEvent;

class RescueEventTest extends \PHPUnit_Framework_TestCase
{
	public function test_instance()
	{
		$route = $this
			->getMockBuilder('ICanBoogie\Routing\Route')
			->disableOriginalConstructor()
			->getMock();

		$exception = new \Exception;
		$request = Request::from('/');
		$response = null;

		/* @var $route Route */

		$event = new RescueEvent($route, $exception, $request, $response);

		$this->assertSame($exception, $event->exception);
		$this->assertSame($request, $event->request);
		$this->assertSame($response, $event->response);

		$exception2 = new \Exception;
		$response2 = new Response;

		$event->exception = $exception2;
		$event->response = $response2;

		$this->assertSame($exception2, $event->exception);
		$this->assertSame($response2, $event->response);
		$this->assertSame($exception2, $exception);
		$this->assertSame($response2, $response);
	}
}
