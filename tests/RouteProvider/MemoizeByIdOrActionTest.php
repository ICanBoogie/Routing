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

use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\MemoizeByIdOrAction;
use PHPUnit\Framework\TestCase;

use function implode;

final class MemoizeByIdOrActionTest extends TestCase
{
    private RouteProvider $provider;
    private SpyRouteProvider $spy;
    private Route $r1;
    private Route $r2;
    private Route $r3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new MemoizeByIdOrAction(
            $this->spy = new SpyRouteProvider(
                new Immutable([
                    $this->r1 = new Route('/', 'page:home', id: 'home'),
                    $this->r2 = new Route('/about.html', 'page:home'),
                    $this->r3 = new Route('/contact.html', 'page:contact'),
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

        $this->assertSame("page:home page:home page:contact", implode(' ', $actions));
        $this->assertEquals(0, $this->spy->times_route_for_predicate);
        $this->assertEquals(1, $this->spy->times_iterator);
    }

    public function test_other_predicates_are_forwarded(): void
    {
        $this->assertSame(
            $this->r1,
            $this->provider->route_for_predicate(fn(Route $route): bool => $route === $this->r1)
        );
        $this->assertEquals(1, $this->spy->times_route_for_predicate);
        $this->assertEquals(0, $this->spy->times_iterator);
    }

    public function test_by_id_or_action(): void
    {
        $this->assertSame($this->r1, $this->provider->route_for_predicate(new ByIdOrAction('home')));
        $this->assertSame($this->r2, $this->provider->route_for_predicate(new ByIdOrAction('page:home')));
        $this->assertSame($this->r3, $this->provider->route_for_predicate(new ByIdOrAction('page:contact')));

        $this->assertEquals(0, $this->spy->times_route_for_predicate);
        $this->assertEquals(2, $this->spy->times_iterator);
    }
}
