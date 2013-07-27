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

class PatternTest extends \PHPUnit_Framework_TestCase
{
	public function testToString()
	{
		$s = '/news/:year-:month-:slug.:format';
		$p = Pattern::from($s);

		$this->assertEquals($s, (string) $p);
	}

	public function testNoPatternButQuery()
	{
		$s = '/api/images/372/thumbnail?w=600&method=fixed-width&quality=80';
		$p = Pattern::from($s);

		$this->assertEquals($s, $p->interleaved[0]);
		$this->assertEmpty($p->params);
		$this->assertEquals('#^' . preg_quote($s) . '$#', $p->regex);
	}

	public function testUnconstrainedPattern()
	{
		$s = '/blog/:categoryslug/:slug.html';
		$p = Pattern::from($s);

		$this->assertEquals('/blog/', $p->interleaved[0]);
		$this->assertEquals(array('categoryslug', '[^/\/]+'), $p->interleaved[1]);
		$this->assertEquals('/', $p->interleaved[2]);
		$this->assertEquals(array('slug', '[^/\.]+'), $p->interleaved[3]);
		$this->assertEquals('.html', $p->interleaved[4]);

		$this->assertEquals('categoryslug', $p->params[0]);
		$this->assertEquals('slug', $p->params[1]);

		$this->assertEquals('#^/blog/([^/\/]+)/([^/\.]+)\.html$#', $p->regex);
	}

	public function testConstrainedPattern()
	{
		$p = Pattern::from('/blog/<categoryslug:[^/]+>/<slug:[^\.]+>.html');

		$this->assertEquals('/blog/', $p->interleaved[0]);
		$this->assertEquals(array('categoryslug', '[^/]+'), $p->interleaved[1]);
		$this->assertEquals('/', $p->interleaved[2]);
		$this->assertEquals(array('slug', '[^\.]+'), $p->interleaved[3]);
		$this->assertEquals('.html', $p->interleaved[4]);

		$this->assertEquals('categoryslug', $p->params[0]);
		$this->assertEquals('slug', $p->params[1]);

		$this->assertEquals('#^/blog/([^/]+)/([^\.]+)\.html$#', $p->regex);
	}

	public function testMatchingAndCapture()
	{
		$pattern = Pattern::from('/news/:year-:month-:slug.:format');

		$rc = $pattern->match('/news/2012-06-this-is-an-example.html', $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html'), $captured);

		$rc = $pattern->match('/news/2012-this-is-an-example.html', $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 'this', 'slug' => 'is-an-example', 'format' => 'html'), $captured);

		# using regex

		$pattern = Pattern::from('/news/<year:\d{4}>-<month:\d{2}>-:slug.:format');

		$rc = $pattern->match('/news/2012-06-this-is-an-example.html', $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html'), $captured);

		#
		# matching should fail because "this" does not match \d{2}
		#

		$rc = $pattern->match('/news/2012-this-is-an-example.html', $captured);

		$this->assertFalse($rc);

		#
		# indexed
		#

		$pattern = Pattern::from('/news/<\d{4}>-<\d{2}>-<[a-z\-]+>.<[a-z]+>');

		$rc = $pattern->match('/news/2012-06-this-is-an-example.html', $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array(2012, 06, 'this-is-an-example', 'html'), $captured);
	}
}