<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(MatchedRoute::class)]
#[UsesClass(Pattern::class)]
#[UsesClass(Route::class)]
final class MatchedRouteTest extends TestCase
{
    public function test_resolve(): void
    {
        $route = new Route('/articles/:id', 'articles:show');
        $request = Request::from([ RequestOptions::OPTION_URI => '/articles/123' ]);
        $request->context->add($route);

        $this->assertSame($route, new MatchedRoute()->resolve($request));
    }

    public function test_export(): void
    {
        $parameter = new MatchedRoute();

        $this->assertEquals($parameter, SetStateHelper::export_import($parameter));
    }
}
