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

use ICanBoogie\Routing\RouteMaker as Make;

class RouteMakeTest extends \PHPUnit_Framework_TestCase
{
	public function test_resource_only_one()
	{
		$routes = Make::resource('photos', 'App\Modules\Photos\Controller', [ 'only' => 'index' ]);

		$this->assertCount(1, $routes);
		$this->assertEquals([ 'photos:index' ], array_keys($routes));
	}

	public function test_resource_only_many()
	{
		$routes = Make::resource('photos', 'App\Modules\Photos\Controller', [ 'only' => [ 'index', 'show' ] ]);

		$this->assertCount(2, $routes);
		$this->assertEquals([ 'photos:index', 'photos:show' ], array_keys($routes));
	}

	public function test_resource_except_one()
	{
		$routes = Make::resource('photos', 'App\Modules\Photos\Controller', [ 'except' => 'delete' ]);

		$this->assertCount(6, $routes);
		$this->assertEquals([ 'photos:index', 'photos:new', 'photos:create', 'photos:show', 'photos:edit', 'photos:update' ], array_keys($routes));
	}

	public function test_resource_except_many()
	{
		$routes = Make::resource('photos', 'App\Modules\Photos\Controller', [ 'except' => [ 'create', 'update', 'delete' ] ]);

		$this->assertCount(4, $routes);
		$this->assertEquals([ 'photos:index', 'photos:new', 'photos:show', 'photos:edit' ], array_keys($routes));
	}

	public function test_resource_as()
	{
		$as = 'build' . uniqid();

		$routes = Make::resource('photos', 'App\Modules\Photos\Controller', [

			'only' => [ 'create', 'show' ],
			'as' => [ 'create' => $as ]

		]);

		$this->assertCount(2, $routes);
		$this->assertArrayHasKey($as, $routes);
		$this->assertEquals($as, $routes[$as]['as']);
		$this->assertArrayHasKey('photos:show', $routes);
		$this->assertEquals('photos:show', $routes['photos:show']['as']);
	}
}
