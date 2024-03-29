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
use PHPUnit\Framework\TestCase;

final class ByActionTest extends TestCase
{
    public function test_predicate(): void
    {
        $predicate = new ByAction('article:list');

        $this->assertFalse($predicate(new Route('/', 'article:home')));
        $this->assertFalse($predicate(new Route('/', 'article:show')));
        $this->assertTrue($predicate(new Route('/', 'article:list')));
    }
}
