<?php

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
