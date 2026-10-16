<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\EncodedValue;
use ICanBoogie\Routing\Exception\NotDecodable;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\Parameter\Body;
use ICanBoogie\Routing\Parameter\Decoded;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use Test\ICanBoogie\Routing\Decoder\Article;
use Test\ICanBoogie\Routing\Decoder\Probe;
use Test\ICanBoogie\Routing\Decoder\Rejecting;
use Test\ICanBoogie\Routing\Decoder\Slug;
use Test\ICanBoogie\Routing\Decoder\SlugDecoder;
use Test\ICanBoogie\Routing\Decoder\Wide;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(Decoded::class)]
#[UsesClass(Body::class)]
#[UsesClass(EncodedValue::class)]
#[UsesClass(NotDecodable::class)]
#[UsesClass(ParameterNotValid::class)]
#[UsesClass(PathParameterNotValid::class)]
final class DecodedTest extends TestCase
{
    public function test_resolve_path(): void
    {
        $parameter = new Decoded('path', 'slug', SlugDecoder::class, Slug::class);
        $slug = $parameter->resolve($this->request(path: [ 'slug' => 'hello' ]));

        $this->assertEquals(new Slug('hello'), $slug);
    }

    public function test_resolve_query(): void
    {
        $parameter = new Decoded('query', 'slug', SlugDecoder::class, Slug::class);
        $slug = $parameter->resolve($this->request(query: [ 'slug' => 'hello', 'other' => 'x' ]));

        $this->assertEquals(new Slug('hello'), $slug);
    }

    public function test_decoder_receives_the_class_to_produce(): void
    {
        $parameter = new Decoded('query', 'slug', Wide::class, Slug::class);
        $slug = $parameter->resolve($this->request(query: [ 'slug' => 'hello' ]));

        $this->assertEquals(new Slug('hello'), $slug);
    }

    public function test_resolve_part(): void
    {
        $parameter = new Decoded('part', 'slug', SlugDecoder::class, Slug::class);
        $slug = $parameter->resolve($this->request(part: [ 'slug' => 'hello' ]));

        $this->assertEquals(new Slug('hello'), $slug);
    }

    public function test_the_decoder_receives_the_value_the_source_and_the_name(): void
    {
        $parameter = new Decoded('part', 'article', Article::class, Article::class);
        $article = $parameter->resolve($this->request(part: [ 'article' => [ 'a' => 1 ] ]));

        $this->assertSame('part:article:{"a":1}', $article->description);
    }

    public function test_resolve_body(): void
    {
        $parameter = new Decoded('body', 'payload', Probe::class, Slug::class);
        $slug = $parameter->resolve($this->request());

        $this->assertSame('body|NULL|SplFileObject|php://memory', $slug->value);
    }

    public function test_resolve_body_is_the_body_stream_of_the_request(): void
    {
        $stream = new SplFileObject('php://temp', 'w+');
        $parameter = new Decoded('body', 'payload', Probe::class, Slug::class);
        $slug = $parameter->resolve(Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_BODY_STREAM => $stream,
        ]));

        $this->assertSame('body|NULL|SplFileObject|php://temp', $slug->value);
    }

    public function test_body_is_never_missing(): void
    {
        $parameter = new Decoded('body', 'payload', Probe::class, Slug::class, nullable: true);

        $this->assertInstanceOf(Slug::class, $parameter->resolve($this->request()));
    }

    public function test_body_not_decodable_is_a_bad_request(): void
    {
        $parameter = new Decoded('body', 'payload', SlugDecoder::class, Slug::class);

        try {
            $parameter->resolve($this->request());
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('body', $e->source);
            $this->assertSame('payload', $e->name);
        }
    }

    #[DataProvider('provide_source')]
    public function test_not_decodable(string $source): void
    {
        $parameter = new Decoded($source, 'slug', SlugDecoder::class, Slug::class);
        $request = $this->request([ 'slug' => 'A B' ], [ 'slug' => 'A B' ], [ 'slug' => 'A B' ]);

        try {
            $parameter->resolve($request);
            $this->fail("Expected exception");
        } catch (PathParameterNotValid | ParameterNotValid $e) {
            $this->assertSame($source === 'path' ? 404 : 400, $e->getCode());
            $this->assertSame('slug', $e->name);
            $this->assertSame(Slug::class, $e->type);
            $this->assertInstanceOf(NotDecodable::class, $e->getPrevious());

            if ($e instanceof ParameterNotValid) {
                $this->assertSame($source, $e->source);
                $this->assertFalse($e->missing);
            }
        }
    }

    /**
     * @return array<string, array{ string }>
     */
    public static function provide_source(): array
    {
        return [ 'path' => [ 'path' ], 'query' => [ 'query' ], 'part' => [ 'part' ] ];
    }

    #[DataProvider('provide_source')]
    public function test_invalid_argument_and_value_error_are_not_decodable(string $source): void
    {
        $parameter = new Decoded($source, 'slug', Rejecting::class, Slug::class);

        foreach ([ 'invalid-argument', 'value-error' ] as $value) {
            $request = $this->request([ 'slug' => $value ], [ 'slug' => $value ], [ 'slug' => $value ]);

            try {
                $parameter->resolve($request);
                $this->fail("Expected exception");
            } catch (PathParameterNotValid | ParameterNotValid $e) {
                $this->assertNotNull($e->getPrevious());
            }
        }
    }

    public function test_other_exceptions_propagate(): void
    {
        $parameter = new Decoded('query', 'slug', Rejecting::class, Slug::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("A bug");

        $parameter->resolve($this->request(query: [ 'slug' => 'bug' ]));
    }

    #[DataProvider('provide_decoded_source')]
    public function test_missing_is_a_bad_request(string $source): void
    {
        $parameter = new Decoded($source, 'slug', SlugDecoder::class, Slug::class);

        try {
            $parameter->resolve($this->request());
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertTrue($e->missing);
            $this->assertSame("The $source parameter 'slug' is required.", $e->getMessage());
        }
    }

    /**
     * @return array<string, array{ string }>
     */
    public static function provide_decoded_source(): array
    {
        return [ 'query' => [ 'query' ], 'part' => [ 'part' ] ];
    }

    public function test_null_is_missing_and_the_decoder_is_not_called(): void
    {
        $parameter = new Decoded('part', 'slug', Rejecting::class, Slug::class, true);

        $this->assertNull($parameter->resolve($this->request(part: [ 'slug' => null ])));
    }

    public function test_missing_uses_the_default(): void
    {
        $parameter = new Decoded('query', 'sort', SlugDecoder::class, Slug::class, has_default: true, default: 'asc');

        $this->assertSame('asc', $parameter->resolve($this->request()));
    }

    public function test_missing_nullable_is_null(): void
    {
        $parameter = new Decoded('query', 'slug', SlugDecoder::class, Slug::class, nullable: true);

        $this->assertNull($parameter->resolve($this->request()));
    }

    public function test_missing_path_value_is_a_wiring_mistake(): void
    {
        $parameter = new Decoded('path', 'slug', SlugDecoder::class, Slug::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The path parameter 'slug' is not available.");

        $parameter->resolve($this->request());
    }

    public function test_missing_optional_path_value(): void
    {
        $parameter = new Decoded('path', 'slug', SlugDecoder::class, Slug::class, nullable: true);

        $this->assertNull($parameter->resolve($this->request()));
    }

    public function test_export(): void
    {
        $parameter = new Decoded('query', 'slug', SlugDecoder::class, Slug::class, true, true, null);
        $imported = SetStateHelper::export_import($parameter);

        $this->assertEquals($parameter, $imported);
    }

    /**
     * @param array<string, mixed> $path
     * @param array<string, mixed> $query
     * @param array<string, mixed> $part
     */
    private function request(array $path = [], array $query = [], array $part = []): Request
    {
        return Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_PATH_PARAMS => $path,
            RequestOptions::OPTION_QUERY_PARAMS => $query,
            RequestOptions::OPTION_REQUEST_PARAMS => $part,
        ]);
    }
}
