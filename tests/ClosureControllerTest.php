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

class ClosureControllerTest extends \PHPUnit\Framework\TestCase
{
	public function testAction()
	{
		$params = [

			'one' => uniqid(),
			'two' => uniqid(),
			'three' => uniqid()

		];

		$request = Request::from([

			Request::OPTION_PATH_PARAMS => $params

		]);

		$test_case = $this;

		$closure = function () use ($test_case, $request, $params) {

			/* @var $this \ICanBoogie\Routing\ClosureController */

			$test_case->assertInstanceOf(ClosureController::class, $this);
			$test_case->assertSame($request, $this->request);
			$test_case->assertSame(array_values($params), func_get_args());

		};

		$controller = new ClosureController($closure);
		$controller($request);
	}
}
