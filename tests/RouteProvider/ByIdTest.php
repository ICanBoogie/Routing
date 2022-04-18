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
use ICanBoogie\Routing\RouteProvider\ById;
use PHPUnit\Framework\TestCase;

final class ByIdTest extends TestCase
{
    public function test_predicate(): void
    {
        $predicate = new ById('article:list');

        $this->assertFalse($predicate(new Route('/', 'some action')));
        $this->assertFalse($predicate(new Route('/', 'some action', id: 'article:show')));
        $this->assertTrue($predicate(new Route('/', 'some action', id: 'article:list')));
    }
}
