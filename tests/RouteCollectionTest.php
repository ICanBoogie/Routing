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

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Exception\InvalidPattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteCollection;
use ICanBoogie\Routing\RouteMaker;
use ICanBoogie\Routing\RouteMaker\Options;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ByUri;
use PHPUnit\Framework\TestCase;

use function array_push;
use function uniqid;

final class RouteCollectionTest extends TestCase
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

		new RouteCollection([ new Route('', 'article:list') ]);
	}

	public function test_iterator(): void
	{
		$routes = new RouteCollection([
			$r1 = new Route('/', 'article:list'),
			$r2 = new Route('/', 'article:list'),
			$r3 = new Route('/', 'article:list'),
		]);

		$this->assertSame([ $r1, $r2, $r3 ], self::to_array($routes));
	}

	public function test_route_for_predicate_by_action(): void
	{
		$routes = new RouteCollection([

			$home = new Route('/', 'home'),
			$edit = new Route('/articles/new', 'articles:edit', RequestMethod::METHOD_GET),
			$list = new Route('/articles', 'articles', [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]),
			$delete = new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),

		]);

		$this->assertSame($home, $routes->route_for_predicate(new ByAction('home')));
		$this->assertSame($list, $routes->route_for_predicate(new ByAction('articles')));
		$this->assertSame($edit, $routes->route_for_predicate(new ByAction('articles:edit')));
		$this->assertSame($delete, $routes->route_for_predicate(new ByAction('articles:delete')));
	}

	public function test_route_for_predicate_by_uri(): void
	{
		$routes = new RouteCollection([

			$home = new Route('/', 'home'),
			new Route('/articles/new', 'articles:edit', RequestMethod::METHOD_GET),
			$create = new Route('/articles', 'articles', [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]),
			$delete = new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),

		]);

		$this->assertSame(
			$home,
			$routes->route_for_predicate(
				new ByUri('/')
			)
		);
		$this->assertSame(
			$home,
			$routes->route_for_predicate(
				new ByUri('/', RequestMethod::METHOD_PATCH)
			)
		);
		$this->assertSame(
			$home,
			$routes->route_for_predicate(
				$p = new ByUri('/?singer=madonna', RequestMethod::METHOD_ANY)
			)
		);
		$this->assertEmpty($p->path_params);
		$this->assertEquals([ 'singer' => 'madonna' ], $p->query_params);

		$this->assertNull($routes->route_for_predicate(new ByUri('/undefined')));
		$this->assertNull(
			$routes->route_for_predicate(
				$p = new ByUri('/undefined?madonna', RequestMethod::METHOD_ANY)
			)
		);
		$this->assertEmpty($p->path_params);

		$this->assertSame($create, $routes->route_for_predicate(new ByUri('/articles')));
		$this->assertSame(
			$create,
			$routes->route_for_predicate(
				new ByUri('/articles', RequestMethod::METHOD_POST)
			)
		);
		$this->assertSame(
			$create,
			$routes->route_for_predicate(
				new ByUri('/articles', RequestMethod::METHOD_PATCH)
			)
		);
		$this->assertNull(
			$routes->route_for_predicate(
				new ByUri('/articles', RequestMethod::METHOD_GET)
			)
		);

		$this->assertSame(
			$delete,
			$routes->route_for_predicate(
				$p = new ByUri('/articles/123', RequestMethod::METHOD_DELETE)
			)
		);
		$this->assertEquals([ 'nid' => 123 ], $p->path_params);
		// Parameters already captured from the path are discarded from the query.
		$this->assertSame(
			$delete,
			$routes->route_for_predicate(
				$p = new ByUri('/articles/123?nid=456&singer=madonna', RequestMethod::METHOD_DELETE)
			)
		);
		$this->assertEquals([ 'nid' => 123 ], $p->path_params);
		$this->assertEquals([ 'singer' => 'madonna' ], $p->query_params);

		$this->assertNull($routes->route_for_predicate(new ByUri('/to/the/articles')));
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

		$this->assertSame(
			$ok,
			$routes->route_for_predicate(
				new ByUri(
					'/api/articles/123/active',
					RequestMethod::METHOD_PUT
				)
			)
		);
	}

	public function test_nameless_capture(): void
	{
		$routes = new RouteCollection([

			$ok = new Route('/admin/articles/<\d+>/edit', 'admin:articles/edit'),

		]);

		$this->assertSame(
			$ok,
			$routes->route_for_predicate(
				$p = new ByUri('/admin/articles/123/edit', RequestMethod::METHOD_ANY)
			)
		);
		$this->assertEquals([ 123 ], $p->path_params);
	}

	public function test_resources(): void
	{
		$routes = new RouteCollection();
		$routes->resource('photos', new Options(only: [ RouteMaker::ACTION_LIST, RouteMaker::ACTION_SHOW ]));
		$actions = [];

		foreach ($routes as $route) {
			$actions[] = $route->action;
		}

		$this->assertSame([ 'photos:list', 'photos:show' ], $actions);
	}

	public function test_filter(): void
	{
		$routes = new RouteCollection([

			$ok = new Route('/admin/articles', 'admin:articles:list'),
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

	public function test_routes_with_multiple_methods(): void
	{
		$routes = new RouteCollection([
			$list = new Route(
				'/articles',
				'articles:list',
				[ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ]
			),

			$show = new Route(
				'/<year:\d{4}>-<month:\d{2}>-:slug',
				'articles:show',
				[ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ]
			),
		]);

		$this->assertSame(
			$list,
			$routes->route_for_predicate(new ByUri('/articles'))
		);

		$this->assertSame(
			$show,
			$routes->route_for_predicate(new ByUri('/2022-04-madonna'))
		);
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
