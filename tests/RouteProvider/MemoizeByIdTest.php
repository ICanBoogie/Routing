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
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\MemoizeById;
use PHPUnit\Framework\TestCase;

use function implode;

final class MemoizeByIdTest extends TestCase
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
        $routes = new MemoizeById($this->routes);

        $this->assertNull($routes->route_for_predicate(new ById('page:home')));
        $this->assertSame($this->r2, $routes->route_for_predicate(new ById('about')));
        $this->assertNull($routes->route_for_predicate(new ById('page:contact')));

        $actions = [];

        foreach ($routes as $route) {
            $actions[] = $route->action;
        }

        $this->assertSame("page:home page:about page:contact", implode(' ', $actions));
    }

    /**
     * The inner provider's iterator is only obtained once.
     */
    public function test_by_id(): void
    {
        $provider = $this->createMock(IterableRouteProvider::class);
        $provider->expects($this->once())
            ->method('getIterator')
            ->willReturn(new ArrayIterator([ $this->r1, $this->r2, $this->r3 ]));

        $routes = new MemoizeById($provider);

        $this->assertNull($routes->route_for_predicate(new ById('madonna')));
        $this->assertSame($this->r2, $routes->route_for_predicate(new ById('about')));
    }
}
