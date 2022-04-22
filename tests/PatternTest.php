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

use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\PatternRequiresValues;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\PatternTest\WithToSlug;

final class PatternTest extends TestCase
{
    public function test_is_pattern(): void
    {
        $this->assertTrue(Pattern::is_pattern('/<year:\d{4}>'));
        $this->assertTrue(Pattern::is_pattern('/articles/:slug'));
        $this->assertTrue(Pattern::is_pattern('/articles/*'));
        $this->assertFalse(Pattern::is_pattern('/path/to/somewhere.html'));
    }

    public function test_from_should_return_same(): void
    {
        $s = '/articles/:slug';
        $p = Pattern::from($s);
        $this->assertSame($p, Pattern::from($s));
        $this->assertSame($p, Pattern::from($p));
    }

    public function test_to_string(): void
    {
        $s = '/news/:year-:month-:slug.:format';
        $p = Pattern::from($s);

        $this->assertEquals($s, (string) $p);
    }

    public function testNoPatternButQuery(): void
    {
        $s = '/api/images/372/thumbnail?w=600&method=fixed-width&quality=80';
        $p = Pattern::from($s);

        $this->assertEquals($s, $p->interleaved[0]);
        $this->assertEmpty($p->params);
        $this->assertEquals('#^' . preg_quote($s) . '$#', $p->regex);
    }

    public function testUnconstrainedPattern(): void
    {
        $s = '/blog/:categoryslug/:slug.html';
        $p = Pattern::from($s);

        $this->assertEquals('/blog/', $p->interleaved[0]);
        $this->assertEquals([ 'categoryslug', '[^/\/]+' ], $p->interleaved[1]);
        $this->assertEquals('/', $p->interleaved[2]);
        $this->assertEquals([ 'slug', '[^/\.]+' ], $p->interleaved[3]);
        $this->assertEquals('.html', $p->interleaved[4]);

        $this->assertEquals('categoryslug', $p->params[0]);
        $this->assertEquals('slug', $p->params[1]);

        $this->assertEquals('#^/blog/([^/\/]+)/([^/\.]+)\.html$#', $p->regex);
    }

    public function testConstrainedPattern(): void
    {
        $p = Pattern::from('/blog/<categoryslug:[^/]+>/<slug:[^\.]+>.html');

        $this->assertEquals('/blog/', $p->interleaved[0]);
        $this->assertEquals([ 'categoryslug', '[^/]+' ], $p->interleaved[1]);
        $this->assertEquals('/', $p->interleaved[2]);
        $this->assertEquals([ 'slug', '[^\.]+' ], $p->interleaved[3]);
        $this->assertEquals('.html', $p->interleaved[4]);

        $this->assertEquals('categoryslug', $p->params[0]);
        $this->assertEquals('slug', $p->params[1]);

        $this->assertEquals('#^/blog/([^/]+)/([^\.]+)\.html$#', $p->regex);
    }

    public function test_should_match_pathname(): void
    {
        $pathname = '/gifs/cats.html';
        $pattern = Pattern::from($pathname);
        $this->assertEquals($pathname, $pattern->pattern);
        $this->assertTrue($pattern->matches($pathname));
    }

    public function test_should_catch_them_all(): void
    {
        $pattern = Pattern::from('/articles/2014-*');
        $this->assertTrue($pattern->matches('/articles/2014-', $capture));
        $this->assertEquals([ 'all' => '' ], $capture);
        $this->assertTrue($pattern->matches('/articles/2014-madonna', $capture));
        $this->assertEquals([ 'all' => 'madonna' ], $capture);
        $this->assertTrue($pattern->matches('/articles/2014-lady-gaga', $capture));
        $this->assertEquals([ 'all' => 'lady-gaga' ], $capture);
        $this->assertFalse($pattern->matches('/articles/2015-lady-gaga', $capture));
        $this->assertEmpty($capture);
    }

    public function testMatchingAndCapture(): void
    {
        $pattern = Pattern::from('/news/:year-:month-:slug.:format');

        $rc = $pattern->matches('/news/2012-06-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html' ],
            $captured
        );

        $rc = $pattern->matches('/news/2012-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 'this', 'slug' => 'is-an-example', 'format' => 'html' ],
            $captured
        );

        # using regex

        $pattern = Pattern::from('/news/<year:\d{4}>-<month:\d{2}>-:slug.:format');

        $rc = $pattern->matches('/news/2012-06-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html' ],
            $captured
        );

        #
        # matching should fail because "this" does not matches \d{2}
        #

        $rc = $pattern->matches('/news/2012-this-is-an-example.html', $captured);

        $this->assertFalse($rc);

        #
        # indexed
        #

        $pattern = Pattern::from('/news/<\d{4}>-<\d{2}>-<[a-z\-]+>.<[a-z]+>');

        $rc = $pattern->matches('/news/2012-06-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals([ 2012, 06, 'this-is-an-example', 'html' ], $captured);
    }

    public function test_to_slug(): void
    {
        $pattern = Pattern::from('/categories/:category/:slug.html');

        $this->assertEquals(
            '/categories/mathieu/ete-2000.html',
            $pattern->format([

                'category' => new WithToSlug('Mathieu'),
                'slug' => new WithToSlug("Été 2000")

            ])
        );
    }

    public function test_unnamed_params(): void
    {
        $pattern = Pattern::from('/admin/dealers/<\d+>/edit/<\d+>');

        $this->assertEquals("/admin/dealers/123/edit/456", $pattern->format([ 123, 456 ]));
    }

    public function test_named_params(): void
    {
        $object = (object) [

            'nid' => 123,
            'slug' => "madonna"

        ];

        $this->assertEquals("/news/123-madonna.html", Pattern::from("/news/:nid-:slug.html")->format($object));
    }

    public function test_formatting_without_values(): void
    {
        $expected = "just-a-url.html";
        $pattern = Pattern::from($expected);
        $this->assertEquals($expected, $pattern->format());
    }

    public function test_formatting_without_values_when_they_are_required(): void
    {
        $pattern = Pattern::from(":year-:month.html");
        $this->expectException(PatternRequiresValues::class);
        $pattern->format();
    }

    public function test_uuid(): void
    {
        $uuid = "f47ac10b-58cc-4372-a567-0e02b2c3d479";
        $pattern = Pattern::from('/articles/<uuid:{:uuid:}>/edit');
        $match = $pattern->matches("/articles/$uuid/edit", $captured);

        $this->assertTrue($match);
        $this->assertSame($uuid, $captured['uuid']);
    }

    public function test_sha1(): void
    {
        $hash = sha1(uniqid());
        $pattern = Pattern::from('/articles/<hash:{:sha1:}>/edit');
        $match = $pattern->matches("/articles/$hash/edit", $captured);

        $this->assertTrue($match);
        $this->assertSame($hash, $captured['hash']);
    }

    public function test_export(): void
    {
        $pattern = Pattern::from('/admin/dealers/<\d+>/edit/<\d+>');
        $actual = SetStateHelper::export_import($pattern);

        $this->assertEquals($pattern, $actual);
    }
}

namespace Test\ICanBoogie\Routing\PatternTest;

use ICanBoogie\Routing\ToSlug;

use function ICanBoogie\normalize;

class WithToSlug implements ToSlug
{
    public function __construct(private string $title)
    {
    }

    public function to_slug(): string
    {
        return normalize($this->title);
    }
}
