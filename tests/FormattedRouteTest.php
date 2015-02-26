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

class FormattedRouteTest extends \PHPUnit_Framework_TestCase
{
	public function test_get_url()
	{
		$url = '/' . uniqid();
		$route = $this
			->getMockBuilder('ICanBoogie\Routing\Route')
			->disableOriginalConstructor()
			->getMock();

		/* @var $route Rooute */

		$ft = new FormattedRoute($url, $route);
		$this->assertSame($url, $ft->url);
		$this->assertSame($route, $ft->route);
	}
}
