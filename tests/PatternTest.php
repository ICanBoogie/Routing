<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\Exception\InvalidPattern;
use ICanBoogie\Routing\Pattern;
use WeakReference;
use ICanBoogie\Routing\PatternRequiresValues;
use ICanBoogie\Routing\PatternValueMissing;
use ICanBoogie\Routing\PatternValueNotValid;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_instances_are_freed_when_unused(): void
    {
        $s = '/weak/:id';
        $p = Pattern::from($s);
        $ref = WeakReference::create($p);
        $this->assertSame($p, Pattern::from($s));

        unset($p);

        $this->assertNull($ref->get());
        $this->assertNotNull(Pattern::from($s));
    }

    public function test_set_state_does_not_evict_cached(): void
    {
        $s = '/weak-state/:id';
        $p = Pattern::from($s);
        $copy = Pattern::__set_state([
            'pattern' => $p->pattern, 'interleaved' => $p->interleaved, 'params' => $p->params, 'regex' => $p->regex
        ]);

        unset($copy);

        $this->assertSame($p, Pattern::from($s));
    }

    public function test_capturing_group_is_rejected(): void
    {
        $this->expectException(InvalidPattern::class);
        $this->expectExceptionMessage("parameter 'id'");

        Pattern::from('/c/<id:(\d+)>');
    }

    public function test_non_capturing_group_is_accepted(): void
    {
        $p = Pattern::from('/c/<id:(?:a|b)+>');

        $this->assertTrue($p->matches('/c/ab', $captured));
        $this->assertSame([ 'id' => 'ab' ], $captured);
    }

    /**
     * @param array<int|string, string> $expected_captured
     */
    #[DataProvider('provide_parsing')]
    public function test_parsing(
        string $pattern,
        string $expected_regex,
        array $expected_params,
        string $uri,
        array $expected_captured,
    ): void {
        $p = Pattern::from($pattern);

        $this->assertSame($expected_regex, $p->regex);
        $this->assertEquals($expected_params, $p->params);
        $this->assertTrue($p->matches($uri, $captured));
        $this->assertEquals($expected_captured, $captured);
    }

    /**
     * @return array<string, array{ string, string, array<int|string>, string, array<int|string, string> }>
     */
    public static function provide_parsing(): array
    {
        return [
            'adjacent parameters' => [ '/:a:b', '#^/([^/]+)([^/]+)$#', [ 'a', 'b' ], '/xy', [ 'a' => 'x', 'b' => 'y' ] ],
            'separator excluded' => [ '/:a.:b', '#^/([^/\\.]+)\\.([^/]+)$#', [ 'a', 'b' ], '/x.y', [ 'a' => 'x', 'b' => 'y' ] ],
            'constraint then shorthand' => [ '/<a:\d+>:b', '#^/(\d+)([^/]+)$#', [ 'a', 'b' ], '/12x', [ 'a' => '12', 'b' => 'x' ] ],
            'anonymous parameters are numbered' => [ '/<\d+>-<\w+>', '#^/(\d+)\\-(\w+)$#', [ 0, 1 ], '/12-ab', [ '12', 'ab' ] ],
            'numeric names' => [ '/<0:\d+>/<1:\d+>', '#^/(\d+)/(\d+)$#', [ 0, 1 ], '/1/2', [ '1', '2' ] ],
            'catchall only' => [ '/x/*', '#^/x/(.*)$#', [ 'all' ], '/x/a/b', [ 'all' => 'a/b' ] ],
            'catchall after parameter' => [ '/:a/*', '#^/([^/\\/]+)/(.*)$#', [ 'a', 'all' ], '/x/y/z', [ 'a' => 'x', 'all' => 'y/z' ] ],
            'extended class' => [ '/<id:{:sha1:}>', '#^/([a-f0-9]{40})$#', [ 'id' ], '/' . str_repeat('a', 40), [ 'id' => str_repeat('a', 40) ] ],
            'trailing literal' => [ '/a/:id/', '#^/a/([^/\\/]+)/$#', [ 'id' ], '/a/1/', [ 'id' => '1' ] ],
        ];
    }

    public function test_interleaved_alternates_literals_and_parameters(): void
    {
        $p = Pattern::from('/:a:b');

        $this->assertSame([ '/', [ 'a', '[^/]+' ], '', [ 'b', '[^/]+' ], '' ], $p->interleaved);
    }

    public function test_leading_parameter_has_empty_first_literal(): void
    {
        $this->assertSame('', Pattern::from(':a/b')->interleaved[0]);
    }

    public function test_invalid_constraint_is_rejected(): void
    {
        $this->expectException(InvalidPattern::class);
        $this->expectExceptionMessage("parameter 'id'");

        Pattern::from('/c/<id:[>');
    }

    public function test_named_group_is_rejected(): void
    {
        $this->expectException(InvalidPattern::class);

        Pattern::from('/c/<id:(?<x>\d+)>');
    }

    public function test_escaped_parenthesis_is_accepted(): void
    {
        $p = Pattern::from('/c/<id:\(\d+\)>');

        $this->assertTrue($p->matches('/c/(12)', $captured));
        $this->assertSame([ 'id' => '(12)' ], $captured);
    }

    public function test_blank_pattern_is_rejected(): void
    {
        $this->expectException(InvalidPattern::class);

        Pattern::from('  ');
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('provide_round_trip')]
    public function test_format_and_matches_round_trip(string $pattern, array $values, string $expected_uri): void
    {
        $p = Pattern::from($pattern);
        $uri = $p->format($values);

        $this->assertSame($expected_uri, $uri);
        $this->assertTrue($p->matches($uri, $captured));
        $this->assertSame($values, $captured);
    }

    /**
     * @return array<string, array{ string, array<string, string>, string }>
     */
    public static function provide_round_trip(): array
    {
        return [
            'space and slash' => [ '/a/:name', [ 'name' => 'a b/c' ], '/a/a%20b%2Fc' ],
            'plus' => [ '/a/:name', [ 'name' => 'a+b' ], '/a/a%2Bb' ],
            'non-ASCII' => [ '/a/:name', [ 'name' => 'café' ], '/a/caf%C3%A9' ],
            'percent' => [ '/a/:name', [ 'name' => '100%' ], '/a/100%25' ],
            'constrained' => [ '/a/<year:\d{4}>/:slug', [ 'year' => '2013', 'slug' => 'é' ], '/a/2013/%C3%A9' ],
        ];
    }

    public function test_encoded_slash_stays_in_its_segment(): void
    {
        $p = Pattern::from('/a/:name');

        $this->assertTrue($p->matches('/a/x%2Fy', $captured));
        $this->assertSame([ 'name' => 'x/y' ], $captured);
        $this->assertFalse($p->matches('/a/x/y'));
    }

    public function test_captured_values_are_decoded_once(): void
    {
        $p = Pattern::from('/a/:name');

        $this->assertTrue($p->matches('/a/%2541', $captured));
        $this->assertSame([ 'name' => '%41' ], $captured);
    }

    public function test_constraint_applies_to_the_raw_value(): void
    {
        $p = Pattern::from('/a/<id:\d+>');

        $this->assertFalse($p->matches('/a/%31'));
    }

    public function test_catchall_is_decoded(): void
    {
        $this->assertTrue(Pattern::from('/files/*')->matches('/files/a%20b/c', $captured));
        $this->assertSame([ 'all' => 'a b/c' ], $captured);
    }

    public function test_invalid_utf8_is_left_to_the_caller(): void
    {
        $this->assertTrue(Pattern::from('/a/:name')->matches('/a/%FF', $captured));
        $this->assertSame([ 'name' => "\xFF" ], $captured);
    }

    public function test_catchall_is_kept_in_the_pattern(): void
    {
        $p = Pattern::from('/files/*');

        $this->assertSame('/files/*', $p->pattern);
        $this->assertSame('/files/*', (string) $p);
        $this->assertSame([ '/files/', [ 'all', '.*' ], '' ], $p->interleaved);
    }

    public function test_catchall_is_formatted_and_keeps_slashes(): void
    {
        $p = Pattern::from('/:dir/files/*');
        $uri = $p->format([ 'dir' => 'a b', 'all' => 'x y/z/é' ]);

        $this->assertSame('/a%20b/files/x%20y/z/%C3%A9', $uri);
        $this->assertTrue($p->matches($uri, $captured));
        $this->assertSame([ 'dir' => 'a b', 'all' => 'x y/z/é' ], $captured);
    }

    public function test_catchall_requires_a_value(): void
    {
        $this->expectException(PatternValueMissing::class);
        $this->expectExceptionMessage("parameter 'all'");

        Pattern::from('/files/*')->format([ 'x' => 1 ]);
    }

    public function test_parameter_named_all_is_not_a_catchall(): void
    {
        $this->assertSame('/a%2Fb%2Fc', Pattern::from('/:all')->format([ 'all' => 'a/b/c' ]));
    }

    #[DataProvider('provide_duplicate_params')]
    public function test_duplicate_parameter_is_rejected(string $pattern, string $name): void
    {
        $this->expectException(InvalidPattern::class);
        $this->expectExceptionMessage("Parameter '$name' is defined twice");

        Pattern::from($pattern);
    }

    /**
     * @return array<string, array{ string, string }>
     */
    public static function provide_duplicate_params(): array
    {
        return [
            'shorthand' => [ '/:a/:a', 'a' ],
            'constrained' => [ '/<a:\d+>/<a:\w+>', 'a' ],
            'mixed' => [ '/:a/<a:\d+>', 'a' ],
            'catch-all' => [ '/:all/*', 'all' ],
            'numeric' => [ '/<0:\d+>/<\d+>', '0' ],
        ];
    }

    public function test_missing_value_in_array(): void
    {
        $p = Pattern::from('/a/:id/b/:slug');

        try {
            $p->format([ 'id' => 1 ]);
            $this->fail("Expected exception");
        } catch (PatternValueMissing $e) {
            $this->assertSame($p, $e->pattern);
            $this->assertSame('slug', $e->name);
            $this->assertStringContainsString('/a/:id/b/:slug', $e->getMessage());
        }
    }

    public function test_null_value_is_missing(): void
    {
        $this->expectException(PatternValueMissing::class);

        Pattern::from('/a/:id')->format([ 'id' => null ]);
    }

    public function test_missing_value_in_object(): void
    {
        $this->expectException(PatternValueMissing::class);
        $this->expectExceptionMessage("parameter 'id'");

        Pattern::from('/a/:id')->format((object) [ 'slug' => 'x' ]);
    }

    public function test_format_from_object(): void
    {
        $this->assertSame('/a/12', Pattern::from('/a/:id')->format((object) [ 'id' => 12 ]));
    }

    public function test_zero_is_a_value(): void
    {
        $this->assertSame('/a/0', Pattern::from('/a/:id')->format([ 'id' => 0 ]));
    }

    public function test_value_that_cannot_be_formatted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('array');

        Pattern::from('/a/:id')->format([ 'id' => [ 1 ] ]);
    }

    public function test_captured_pairs_and_decodes(): void
    {
        $this->assertSame(
            [ 'a' => 'x y', 'b' => '/' ],
            Pattern::from('/:a/:b')->captured([ 'x%20y', '%2F' ])
        );
    }

    #[DataProvider('provide_invalid_values')]
    public function test_value_must_satisfy_the_constraint(string $pattern, array $values, string $name): void
    {
        $p = Pattern::from($pattern);

        try {
            $p->format($values);
            $this->fail("Expected exception");
        } catch (PatternValueNotValid $e) {
            $this->assertSame($p, $e->pattern);
            $this->assertSame($name, $e->name);
            $expected = $values[$name];
            $this->assertSame($expected instanceof WithToSlug ? $expected->to_slug() : (string) $expected, $e->value);
            $this->assertStringContainsString($pattern, $e->getMessage());
        }
    }

    /**
     * @return array<string, array{ string, array<string, mixed>, string }>
     */
    public static function provide_invalid_values(): array
    {
        return [
            'digits' => [ '/a/<id:\d+>', [ 'id' => 'x' ], 'id' ],
            'empty' => [ '/a/:slug', [ 'slug' => '' ], 'slug' ],
            'empty, constrained' => [ '/a/<id:\d*x>', [ 'id' => '1' ], 'id' ],
            'partial match' => [ '/a/<id:\d+>', [ 'id' => '12x' ], 'id' ],
            'encoded value is tested' => [ '/a/<slug:[\w-]+>', [ 'slug' => 'café' ], 'slug' ],
            'separator of the shorthand' => [ '/blog/:slug.html', [ 'slug' => 'v1.2' ], 'slug' ],
            'second parameter' => [ '/a/<y:\d{4}>/<m:\d{2}>', [ 'y' => '2013', 'm' => '7' ], 'm' ],
            'alternatives' => [ '/a/<size:\d+x|x\d+|\d+x\d+>', [ 'size' => 'big' ], 'size' ],
            'extended class' => [ '/a/<id:{:sha1:}>', [ 'id' => 'xyz' ], 'id' ],
            'slug object' => [ '/a/<id:\d+>', [ 'id' => new WithToSlug('nope') ], 'id' ],
        ];
    }

    #[DataProvider('provide_valid_values')]
    public function test_valid_values_are_formatted_and_matched(string $pattern, array $values): void
    {
        $p = Pattern::from($pattern);

        $this->assertTrue($p->matches($p->format($values)));
    }

    /**
     * @return array<string, array{ string, array<string, mixed> }>
     */
    public static function provide_valid_values(): array
    {
        return [
            'digits' => [ '/a/<id:\d+>', [ 'id' => 12 ] ],
            'zero' => [ '/a/<id:\d+>', [ 'id' => 0 ] ],
            'alternatives' => [ '/a/<size:\d+x|x\d+|\d+x\d+>', [ 'size' => '10x20' ] ],
            'empty allowed' => [ '/a/<id:\d*>', [ 'id' => '' ] ],
            'sha1' => [ '/a/<id:{:sha1:}>', [ 'id' => str_repeat('a', 40) ] ],
            'dot without separator' => [ '/a/:v', [ 'v' => '1.2' ] ],
            'catch-all, anything' => [ '/f/*', [ 'all' => 'a b/c.d' ] ],
        ];
    }

    public function test_to_string(): void
    {
        $s = '/news/:year-:month-:slug.:format';
        $p = Pattern::from($s);

        $this->assertEquals($s, (string)$p);
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
        $this->assertTrue($pattern->matches('/articles/2014-', $captured));
        $this->assertEquals([ 'all' => '' ], $captured);
        $this->assertTrue($pattern->matches('/articles/2014-madonna', $captured));
        $this->assertEquals([ 'all' => 'madonna' ], $captured);
        $this->assertTrue($pattern->matches('/articles/2014-lady-gaga', $captured));
        $this->assertEquals([ 'all' => 'lady-gaga' ], $captured);
        $this->assertFalse($pattern->matches('/articles/2015-lady-gaga', $captured));
        $this->assertEmpty($captured);
    }

    public function testMatchingAndCapture(): void
    {
        $pattern = Pattern::from('/news/:year-:month-:slug.:format');

        $rc = $pattern->matches('/news/2012-06-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html' ],
            $captured,
        );

        $rc = $pattern->matches('/news/2012-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 'this', 'slug' => 'is-an-example', 'format' => 'html' ],
            $captured,
        );

        # using regex

        $pattern = Pattern::from('/news/<year:\d{4}>-<month:\d{2}>-:slug.:format');

        $rc = $pattern->matches('/news/2012-06-this-is-an-example.html', $captured);

        $this->assertTrue($rc);
        $this->assertEquals(
            [ 'year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html' ],
            $captured,
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
                'slug' => new WithToSlug("Été 2000"),

            ]),
        );
    }

    public function test_unnamed_params(): void
    {
        $pattern = Pattern::from('/admin/dealers/<\d+>/edit/<\d+>');

        $this->assertEquals("/admin/dealers/123/edit/456", $pattern->format([ 123, 456 ]));
    }

    public function test_named_params(): void
    {
        $object = (object)[

            'nid' => 123,
            'slug' => "madonna",

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
        $captured = [];
        $match = $pattern->matches("/articles/$uuid/edit", $captured);

        $this->assertTrue($match);
        $this->assertSame($uuid, $captured['uuid']);
    }

    public function test_sha1(): void
    {
        $hash = sha1(uniqid());
        $pattern = Pattern::from('/articles/<hash:{:sha1:}>/edit');
        $captured = [];
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
