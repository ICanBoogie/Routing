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

use ICanBoogie\Routing\RouteCollection;
use ICanBoogie\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    public function test_generate_url(): void
    {
        $routes = new RouteCollection();
        $routes->resource('articles');

        $generator = new UrlGenerator($routes);
        $url = $generator->generate_url('articles:show', [ 'id' => 123 ]);

        $this->assertEquals("/articles/123", $url);
    }
}
