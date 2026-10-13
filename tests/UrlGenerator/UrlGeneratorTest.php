<?php

namespace Test\ICanBoogie\Routing\UrlGenerator;

use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\UrlGenerator\UrlGeneratorWithRouteProvider;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function test_generate_url(): void
    {
        $routes = new Immutable([
            new Route('/articles', 'articles:list'),
            new Route('/articles/:id', 'articles:show'),
        ]);

        $generator = new UrlGeneratorWithRouteProvider($routes);

        $url = $generator->generate_url('articles:show', [ 'id' => 123, 'title' => 'madonna' ]);
        $this->assertEquals("/articles/123", $url);

        $url = $generator->generate_url('articles:list', query_params: [ 'page' => 1, 'order' => '-date' ]);
        $this->assertEquals("/articles?page=1&order=-date", $url);
    }
}
