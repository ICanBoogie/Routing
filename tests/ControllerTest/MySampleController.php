<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ControllerTest;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;
use ICanBoogie\Routing\Route;

class MySampleController extends Controller
{
	protected function action(Request $request)
	{
		$request->test->assertInstanceOf(Request::class, $request);
		$request->test->assertEquals(1, func_num_args());
		$request->test->assertEquals("my_sample", $this->name);
		$request->test->assertInstanceOf(Route::class, $this->route);

		return 'HERE';
	}
}
