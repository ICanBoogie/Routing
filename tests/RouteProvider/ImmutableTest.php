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
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\Immutable;
use PHPUnit\Framework\TestCase;

use function implode;

final class ImmutableTest extends TestCase
{
    public function test_routes(): void
    {
        $routes = new Immutable([
            $r1 = new Route('/', 'page:home'),
            $r2 = new Route('/about.html', 'page:about'),
            $r3 = new Route('/contact.html', 'page:contact'),
        ]);

        $this->assertSame($r1, $routes->route_for_predicate(new ByAction('page:home')));
        $this->assertSame($r2, $routes->route_for_predicate(new ByAction('page:about')));
        $this->assertSame($r3, $routes->route_for_predicate(new ByAction('page:contact')));
        $this->assertNull($routes->route_for_predicate(new ByAction('madonna')));

        $actions = [];

        foreach ($routes as $route) {
            $actions[] = $route->action;
        }

        $this->assertSame("page:home page:about page:contact", implode(' ', $actions));
    }
}
