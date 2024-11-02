<?php

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
