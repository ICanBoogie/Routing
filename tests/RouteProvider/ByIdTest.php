<?php

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
