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

use ArrayIterator;
use ICanBoogie\Routing\IterableRouteProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\MemoizeByAction;
use PHPUnit\Framework\TestCase;

use function implode;

final class MemoizeByActionTest extends TestCase
{
    private Immutable $routes;
    private Route $r1;
    private Route $r2;
    private Route $r3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = new Immutable([
            $this->r1 = new Route('/', 'page:home'),
            $this->r2 = new Route('/about.html', 'page:about', id: 'about'),
            $this->r3 = new Route('/contact.html', 'page:contact'),
        ]);
    }

    public function test_routes(): void
    {
        $routes = new MemoizeByAction($this->routes);

        $this->assertSame($this->r1, $routes->route_for_predicate(new ByAction('page:home')));
        $this->assertSame($this->r2, $routes->route_for_predicate(new ByAction('page:about')));
        $this->assertSame($this->r3, $routes->route_for_predicate(new ByAction('page:contact')));
        $this->assertNull($routes->route_for_predicate(new ByAction('madonna')));
        $this->assertSame($this->r2, $routes->route_for_predicate(new ById('about')));

        $actions = [];

        foreach ($routes as $route) {
            $actions[] = $route->action;
        }

        $this->assertSame("page:home page:about page:contact", implode(' ', $actions));
    }

    /**
     * The inner provider's iterator is only obtained once.
     */
    public function test_by_action(): void
    {
        $provider = $this->createMock(IterableRouteProvider::class);
        $provider->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([ $this->r1, $this->r2, $this->r3 ]));

        $routes = new MemoizeByAction($provider);

        $this->assertNull($routes->route_for_predicate(new RouteProvider\ByAction('madonna')));
        $this->assertSame($this->r2, $routes->route_for_predicate(new RouteProvider\ByAction('page:about')));
    }
}
