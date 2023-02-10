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
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\Immutable;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

use function implode;

final class ImmutableTest extends TestCase
{
    private RouteProvider $provider;
    private Route $r1;
    private Route $r2;
    private Route $r3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new Immutable([
            $this->r1 = new Route('/', 'page:home'),
            $this->r2 = new Route('/about.html', 'page:about'),
            $this->r3 = new Route('/contact.html', 'page:contact'),
        ]);
    }
    public function test_routes(): void
    {
        $this->assertSame($this->r1, $this->provider->route_for_predicate(new ByAction('page:home')));
        $this->assertSame($this->r2, $this->provider->route_for_predicate(new ByAction('page:about')));
        $this->assertSame($this->r3, $this->provider->route_for_predicate(new ByAction('page:contact')));
        $this->assertNull($this->provider->route_for_predicate(new ByAction('madonna')));

        $actions = [];

        foreach ($this->provider as $route) {
            $actions[] = $route->action;
        }

        $this->assertSame("page:home page:about page:contact", implode(' ', $actions));
    }

    public function test_export(): void
    {
        $actual = SetStateHelper::export_import($this->provider);
        $this->assertEquals($this->provider, $actual);
    }
}
