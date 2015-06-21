<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Controller\ActionTraitTest;

use ICanBoogie\Routing\Controller;

class ActionController extends Controller
{
	use Controller\ActionTrait;

	protected function action_view($year, $month, $slug)
	{
		$test = $this->request->test;
		$test->assertEquals('view', $this->action);
		$test->assertEquals(3, func_num_args());
		$test->assertEquals(2014, $year);
		$test->assertEquals(12, $month);
		$test->assertEquals("my-awesome-post", $slug);

		return 'HERE';
	}
}
