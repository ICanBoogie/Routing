<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\Exception\RouteNotDefined;
use PHPUnit\Framework\TestCase;

class RouteNotDefinedTest extends TestCase
{
	protected function setUp(): void
	{
		$this->markTestIncomplete();
	}

	public function test_instance(): void
	{
		$id = 'id' . uniqid();
		$instance = new RouteNotDefined($id);
		$this->assertSame($id, $instance->id);
		$this->assertSame(404, $instance->getCode());
	}
}
