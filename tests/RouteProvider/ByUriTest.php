<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;

final class ByUriTest extends TestCase
{
	public function test_predicate(): void
	{
		$predicate = new ByUri('/articles/123?order=-date&nid=456');

		$this->assertFalse($predicate(new Route('/', 'article:home')));
		$this->assertEquals('/articles/123', $predicate->path);
		$this->assertEmpty($predicate->path_params);
		$this->assertEquals([ 'order' => '-date', 'nid' => '456' ], $predicate->query_params);

		$this->assertFalse($predicate(new Route('/articles', 'article:show')));
		$this->assertEquals('/articles/123', $predicate->path);
		$this->assertEmpty($predicate->path_params);
		$this->assertEquals([ 'order' => '-date', 'nid' => '456' ], $predicate->query_params);

		$this->assertTrue($predicate(new Route('/articles/<nid:\d+>', 'article:index')));
		$this->assertEquals('/articles/123', $predicate->path);
		$this->assertEquals([ 'nid' => '123' ], $predicate->path_params);
		$this->assertEquals([ 'order' => '-date' ], $predicate->query_params);
	}
}
