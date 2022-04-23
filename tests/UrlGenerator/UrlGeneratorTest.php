<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\UrlGenerator;

use ICanBoogie\Routing\RouteCollection;
use ICanBoogie\Routing\UrlGenerator\UrlGeneratorWithRouteProvider;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function test_generate_url(): void
    {
        $routes = new RouteCollection();
        $routes->resource('articles');

        $generator = new UrlGeneratorWithRouteProvider($routes);

        $url = $generator->generate_url('articles:show', [ 'id' => 123, 'title' => 'madonna' ]);
        $this->assertEquals("/articles/123", $url);

        $url = $generator->generate_url('articles:list', query_params: [ 'page' => 1, 'order' => '-date' ]);
        $this->assertEquals("/articles?page=1&order=-date", $url);
    }
}
