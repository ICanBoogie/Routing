<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\RouteProvider\ByAction;
use PHPUnit\Framework\TestCase;

use function var_dump;

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
