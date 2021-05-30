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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
	private const DUMMY_ACTION = 'article:show';

	public function test_failure_on_empty_action(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Action cannot be empty.");

		new Route("/", "");
	}

	public function test_get_pattern(): void
	{
		$s = '/news/:year-:month-:slug.:format';
		$r = new Route($s, self::DUMMY_ACTION);

		$this->assertInstanceOf(Pattern::class, $r->pattern);
	}

	public function test_format(): void
	{
		$route = new Route('/news/:year-:month-:slug.html', self::DUMMY_ACTION);

		$formatted_route = $route->format([

			'year' => '2014',
			'month' => '06',
			'slug' => 'madonna-queen-of-pop'

		]);

		$expected_url = '/news/2014-06-madonna-queen-of-pop.html';

		$this->assertInstanceOf(FormattedRoute::class, $formatted_route);
		$this->assertEquals($expected_url, (string) $formatted_route);
		$this->assertEquals($expected_url, $formatted_route->url);
		$this->assertEquals("http://icanboogie.org$expected_url", $formatted_route->absolute_url);
	}

	public function test_get_url(): void
	{
		$expected = "/my-awesome-url.html";
		$route = new Route($expected, self::DUMMY_ACTION);
		$this->assertEquals($expected, $route->url);
	}

	public function test_get_url_requiring_values(): void
	{
		$expected = "/:year-:month.html";
		$route = new Route($expected, self::DUMMY_ACTION);
		$this->expectException(PatternRequiresValues::class);
		$this->assertEquals($expected, $route->url);
	}

	public function test_get_absolute_url(): void
	{
		$route = new Route("/my-awesome-url.html", self::DUMMY_ACTION);
		$this->assertEquals("http://icanboogie.org/my-awesome-url.html", $route->absolute_url);
	}

	public function test_with(): void
	{
		$year = uniqid();
		$month = uniqid();
		$formatting_value = [ 'year' => $year, 'month' => $month ];
		$r1 = new Route('/:year-:month.html', self::DUMMY_ACTION);
		$this->assertNull($r1->formatting_value);
		$this->assertFalse($r1->has_formatting_value);

		$r2 = $r1->assign($formatting_value);
		$this->assertInstanceOf(Route::class, $r2);
		$this->assertNotSame($r1, $r2);
		$this->assertSame($formatting_value, $r2->formatting_value);
		$this->assertTrue($r2->has_formatting_value);

		$expected_url = "/$year-$month.html";

		$this->assertSame($expected_url, $r2->url);
		$this->assertSame($expected_url, (string) $r2);
	}

	public function test_should_reset_formatting_value_on_clone(): void
	{
		$formatting_value = [ 'a' => uniqid() ];
		$route = (new Route('/', self::DUMMY_ACTION))->assign($formatting_value);

		$route2 = clone $route;
		$this->assertNull($route2->formatting_value);
		$this->assertFalse($route2->has_formatting_value);
	}
}
