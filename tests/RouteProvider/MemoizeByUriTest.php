<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\ByUri;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\MemoizeByUri;
use PHPUnit\Framework\TestCase;

use function implode;

final class MemoizeByUriTest extends TestCase
{
    private RouteProvider $provider;
    private SpyRouteProvider $spy;
    private Route $r1;
    private Route $r2;
    private Route $r3;
    private Route $create;
    private Route $delete;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new MemoizeByUri(
            $this->spy = new SpyRouteProvider(
                new Immutable([
                    $this->r1 = new Route('/', 'page:home', id: 'home'),
                    $this->r2 = new Route('/about.html', 'page:about'),
                    $this->r3 = new Route('/contact.html', 'page:contact', id: 'contact'),
                    new Route('/articles/new', 'articles:edit', RequestMethod::METHOD_GET),
                    $this->create = new Route(
                        '/articles',
                        'articles:create',
                        [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]
                    ),
                    $this->delete = new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),
                ])
            )
        );
    }

    public function test_iterator(): void
    {
        $actions = [];

        foreach ($this->provider as $route) {
            $actions[] = $route->action;
        }

        $this->assertSame(
            "page:home page:about page:contact articles:edit articles:create articles:delete",
            implode(' ', $actions)
        );
        $this->assertEquals(0, $this->spy->times_route_for_predicate);
        $this->assertEquals(1, $this->spy->times_iterator);
    }

    public function test_other_predicates_are_forwarded(): void
    {
        $this->assertSame($this->r1, $this->provider->route_for_predicate(new ById('home')));
        $this->assertSame($this->r2, $this->provider->route_for_predicate(new ByAction('page:about')));
        $this->assertSame($this->r3, $this->provider->route_for_predicate(new ByAction('page:contact')));
        $this->assertNull($this->provider->route_for_predicate(new ById('madonna')));
        $this->assertEquals(4, $this->spy->times_route_for_predicate);
        $this->assertEquals(0, $this->spy->times_iterator);
    }

    public function test_by_uri(): void
    {
        $this->assertSame(
            $this->r1,
            $this->provider->route_for_predicate(
                new ByUri('/')
            )
        );
        $this->assertSame(
            $this->r1,
            $this->provider->route_for_predicate(
                new ByUri('/', RequestMethod::METHOD_PATCH)
            )
        );
        $this->assertSame(
            $this->r1,
            $this->provider->route_for_predicate(
                $p = new ByUri('/?singer=madonna', RequestMethod::METHOD_ANY)
            )
        );
        $this->assertEmpty($p->path_params);
        $this->assertEquals([ 'singer' => 'madonna' ], $p->query_params);

        $this->assertNull($this->provider->route_for_predicate(new ByUri('/undefined')));
        $this->assertNull(
            $this->provider->route_for_predicate(
                $p = new ByUri('/undefined?madonna', RequestMethod::METHOD_ANY)
            )
        );
        $this->assertEmpty($p->path_params);

        $this->assertSame($this->create, $this->provider->route_for_predicate(new ByUri('/articles')));
        $this->assertSame(
            $this->create,
            $this->provider->route_for_predicate(
                new ByUri('/articles', RequestMethod::METHOD_POST)
            )
        );
        $this->assertSame(
            $this->create,
            $this->provider->route_for_predicate(
                new ByUri('/articles', RequestMethod::METHOD_PATCH)
            )
        );
        $this->assertNull(
            $this->provider->route_for_predicate(
                new ByUri('/articles', RequestMethod::METHOD_GET)
            )
        );

        $this->assertSame(
            $this->delete,
            $this->provider->route_for_predicate(
                $p = new ByUri('/articles/123', RequestMethod::METHOD_DELETE)
            )
        );
        $this->assertEquals([ 'nid' => 123 ], $p->path_params);
        // Parameters already captured from the path are discarded from the query.
        $this->assertSame(
            $this->delete,
            $this->provider->route_for_predicate(
                $p = new ByUri('/articles/123?nid=456&singer=madonna', RequestMethod::METHOD_DELETE)
            )
        );
        $this->assertEquals([ 'nid' => 123 ], $p->path_params);
        $this->assertEquals([ 'singer' => 'madonna' ], $p->query_params);

        $this->assertNull($this->provider->route_for_predicate(new ByUri('/to/the/articles')));

        $this->assertEquals(1, $this->spy->times_iterator);
        $this->assertEquals(0, $this->spy->times_route_for_predicate);
    }

    public function test_weight(): void
    {
        $routes = new MemoizeByUri(
            new Immutable([
                new Route('/api/:constructor/:id/active', 'api:nodes:activate', RequestMethod::METHOD_PUT),
                $ok = new Route('/api/articles/:id/active', 'api:articles:activate', RequestMethod::METHOD_PUT),
            ])
        );

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
        $routes = new MemoizeByUri(
            new Immutable([
                $ok = new Route('/admin/articles/<\d+>/edit', 'admin:articles/edit'),
            ])
        );

        $this->assertSame(
            $ok,
            $routes->route_for_predicate(
                $p = new ByUri('/admin/articles/123/edit', RequestMethod::METHOD_ANY)
            )
        );
        $this->assertEquals([ 123 ], $p->path_params);
    }
}
