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
use ICanBoogie\Routing\Route;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function test_failure_on_empty_action(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The action cannot be blank.");

        new Route("/", "");
    }

    public function test_get_pattern(): void
    {
        $s = '/news/:year-:month-:slug.:format';
        $r = new Route($s, $action = 'article:show', id: $id = 'my-id');

        $this->assertSame($s, $r->pattern->pattern);
        $this->assertSame($action, $r->action);
        $this->assertSame($id, $r->id);
    }

    public function test_export(): void
    {
        $route = new Route(
            '/news/:year-:month-:slug.:format',
            'article:show',
            methods: RequestMethod::METHOD_ANY,
            id: 'my-show',
        );
        $actual = SetStateHelper::export_import($route);

        $this->assertEquals($route, $actual);
    }
}
