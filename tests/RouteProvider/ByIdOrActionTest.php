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
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use PHPUnit\Framework\TestCase;

final class ByIdOrActionTest extends TestCase
{
    public function test_predicate(): void
    {
        $predicate = new ByIdOrAction('articles:home');

        $this->assertTrue($predicate(new Route('/', 'articles:home')));
        $this->assertTrue($predicate(new Route('/', 'articles:home', id: 'articles:list')));
        $this->assertFalse($predicate(new Route('/', 'articles:index', id: 'article:index')));
        $this->assertFalse($predicate(new Route('/', 'articles:show', id: 'article:show')));
    }
}
