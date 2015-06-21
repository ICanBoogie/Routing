<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Routing\Route;

class BeforeDispatchEventTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function test_should_throw_exception_if_response_is_invalid()
	{
		$dispatcher = $this
			->getMockBuilder(Dispatcher::class)
			->disableOriginalConstructor()
			->getMock();

		$route = $this
			->getMockBuilder(Route::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var $dispatcher Dispatcher */
		/* @var $route Route */

		$request = Request::from('/');

		new BeforeDispatchEvent($dispatcher, $route, $request, $dispatcher);
	}
}
