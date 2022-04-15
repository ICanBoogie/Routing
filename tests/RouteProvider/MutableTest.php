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

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Exception\InvalidPattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteCollection;
use ICanBoogie\Routing\RouteMaker;
use ICanBoogie\Routing\RouteMaker\Options;
use PHPUnit\Framework\TestCase;

use function array_push;
use function uniqid;

final class MutableTest extends TestCase
{
	public function test_multiple_routes_may_have_the_same_action(): void
	{
		$routes = new RouteCollection([

			new Route(uniqid(), $action = 'articles:show'),
			new Route(uniqid(), $action),

		]);

		$this->assertCount(2, $routes);
	}

	public function test_should_fail_on_empty_pattern(): void
	{
		$this->expectException(InvalidPattern::class);

		new RouteCollection([ new Route('', 'article:index') ]);
	}

	public function test_iterator(): void
	{
		$routes = new RouteCollection([
			$r1 = new Route('/', 'article:index'),
			$r2 = new Route('/', 'article:index'),
			$r3 = new Route('/', 'article:index'),
		]);

		$this->assertSame([ $r1, $r2, $r3 ], self::to_array($routes));
	}

	public function test_find(): void
	{
		$routes = new RouteCollection([

			$home = new Route('/', 'home'),
			new Route('/articles/new', 'articles:edit', RequestMethod::METHOD_GET),
			$index = new Route('/articles', 'articles', [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]),
			$delete = new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),

		]);

		$this->assertSame($home, $routes->route_for_uri('/'));
		$this->assertSame($home, $routes->route_for_uri('/', RequestMethod::METHOD_PATCH, $path_params));
		$this->assertSame($home, $routes->route_for_uri(
			'/?singer=madonna',
			RequestMethod::METHOD_ANY,
			$path_params,
			$query_params
		));
		$this->assertEmpty($path_params);
		$this->assertEquals([ 'singer' => 'madonna' ], $query_params);

		$this->assertNull($routes->route_for_uri('/undefined'));
		$this->assertNull($routes->route_for_uri(
			'/undefined?madonna',
			RequestMethod::METHOD_ANY,
			$path_params
		));
		$this->assertEmpty($path_params);

		$this->assertSame($index, $routes->route_for_uri('/articles'));
		$this->assertSame($index, $routes->route_for_uri(
			'/articles',
			RequestMethod::METHOD_POST,
			$path_params
		));
		$this->assertSame($index, $routes->route_for_uri(
			'/articles',
			RequestMethod::METHOD_PATCH,
			$path_params
		));
		$this->assertNull($routes->route_for_uri('/articles', RequestMethod::METHOD_GET, $path_params));

		$this->assertSame($delete, $routes->route_for_uri(
			'/articles/123',
			RequestMethod::METHOD_DELETE,
			$path_params
		));
		$this->assertEquals([ 'nid' => 123 ], $path_params);
		// Parameters already captured from the path are discarded from the query.
		$this->assertSame($delete, $routes->route_for_uri(
			'/articles/123?nid=456&singer=madonna',
			RequestMethod::METHOD_DELETE,
			$path_params,
			$query_params
		));
		$this->assertEquals([ 'nid' => 123 ], $path_params);
		$this->assertEquals([ 'singer' => 'madonna' ], $query_params);

		$this->assertNull($routes->route_for_uri('//articles'));
	}

	/**
	 * 'api:articles:activate' should win over 'api:nodes:activate' because more static parts
	 * are defined before the first capture.
	 */
	public function test_weight(): void
	{
		$routes = new RouteCollection([

			new Route('/api/:constructor/:id/active', 'api:nodes:activate', RequestMethod::METHOD_PUT),
			$ok = new Route('/api/articles/:id/active', 'api:articles:activate', RequestMethod::METHOD_PUT),

		]);

		$this->assertSame($ok, $routes->route_for_uri(
			'/api/articles/123/active',
			RequestMethod::METHOD_PUT
		));
	}

	public function test_nameless_capture(): void
	{
		$routes = new RouteCollection([

			$ok = new Route('/admin/articles/<\d+>/edit', 'admin:articles/edit'),

		]);

		$this->assertSame($ok, $routes->route_for_uri(
			'/admin/articles/123/edit',
			RequestMethod::METHOD_ANY,
			$path_params
		));
		$this->assertEquals([ 123 ], $path_params);
	}

	public function test_resources(): void
	{
		$routes = new RouteCollection();
		$routes->resource('photos', new Options(only: [ RouteMaker::ACTION_INDEX, RouteMaker::ACTION_SHOW ]));
		$actions = [];

		foreach ($routes as $route)
		{
			$actions[] = $route->action;
		}

		$this->assertSame([ 'photos:index', 'photos:show' ], $actions);
	}

	public function test_filter(): void
	{
		$routes = new RouteCollection([

			$ok = new Route('/admin/articles', 'admin:articles:index'),
			new Route('/articles/<id:\d+>', 'articles:show'),

		]);

		$filtered_routes = $routes->filter(
			fn(Route $route): bool => str_starts_with($route->action, 'admin:')
		);

		$this->assertNotSame($routes, $filtered_routes);
		$this->assertCount(2, $routes);
		$this->assertCount(1, $filtered_routes);

		$this->assertSame([ $ok ], self::to_array($filtered_routes));
	}

	public function test_route_with_id_is_unique(): void
	{
		$id = uniqid();
		$routes = new RouteCollection([

			new Route('/' . uniqid(), 'admin:' . uniqid(), id: $id),
			new Route('/' . uniqid(), 'admin:' . uniqid(), id: $id),
			new Route('/' . uniqid(), 'admin:' . uniqid(), id: $id),
			$ok = new Route('/' . uniqid(), 'admin:' . uniqid(), id: $id),

		]);

		$this->assertCount(1, $routes);
		$this->assertSame([ $ok ], self::to_array($routes));
	}

	/**
	 * @return Route[]
	 */
	private static function to_array(RouteCollection $routes): array
	{
		$ar = [];

		array_push($ar, ...$routes);

		return $ar;
	}
}
