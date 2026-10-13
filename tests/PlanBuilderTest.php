<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\Parameter;
use ICanBoogie\Routing\ParameterAbstract;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use ICanBoogie\Routing\Parameter\Decoded;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Parameter\Part;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\Parameter\Query;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\PlanBuilder;
use ICanBoogie\Routing\PlanBuilder\DecodedParameterBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Test\ICanBoogie\Routing\Decoder\Article;
use Test\ICanBoogie\Routing\Decoder\Slug;
use Test\ICanBoogie\Routing\Decoder\SlugDecoder;
use Test\ICanBoogie\Routing\Decoder\Wide;
use Test\ICanBoogie\Routing\Parameter\IntBackedEnum;
use Test\ICanBoogie\Routing\Parameter\StringBackedEnum;
use Test\ICanBoogie\Routing\PlanBuilder\PlanController;
use Test\ICanBoogie\Routing\PlanBuilder\Wrapper;

use function array_map;

#[CoversClass(PlanBuilder::class)]
#[CoversClass(DecodedParameterBuilder::class)]
#[UsesClass(\ICanBoogie\Routing\Attribute\Part::class)]
#[UsesClass(\ICanBoogie\Routing\Attribute\Path::class)]
#[UsesClass(\ICanBoogie\Routing\Attribute\Query::class)]
#[UsesClass(ParameterAbstract::class)]
#[UsesClass(Decoded::class)]
#[UsesClass(Pattern::class)]
final class PlanBuilderTest extends TestCase
{
    /**
     * @param list<Parameter> $expected
     * @param string[] $patterns
     */
    #[DataProvider('provide_plan')]
    public function test_plan(string $method, array $patterns, array $expected): void
    {
        $this->assertEquals($expected, PlanBuilder::build($this->method($method), $this->patterns($patterns)));
    }

    /**
     * @return array<string, array{ string, string[], list<Parameter> }>
     */
    public static function provide_plan(): array
    {
        return [

            'no parameters' => [ 'no_parameters', [ '/' ], [] ],
            'path parameter' => [
                'path_parameter',
                [ '/articles/:id' ],
                [ new Path('id', 'int') ]
            ],
            'path parameter with a constraint' => [
                'path_parameter',
                [ '/articles/<id:\d+>' ],
                [ new Path('id', 'int') ]
            ],
            'all scalars' => [
                'all_scalars',
                [ '/:a/:b/:c/:d' ],
                [ new Path('a', 'int'), new Path('b', 'float'), new Path('c', 'string'), new Path('d', 'bool') ]
            ],
            'by name, not by position' => [
                'all_scalars',
                [ '/:d/:c/:b/:a' ],
                [ new Path('a', 'int'), new Path('b', 'float'), new Path('c', 'string'), new Path('d', 'bool') ]
            ],
            'request and route' => [
                'request_and_route',
                [ '/:slug' ],
                [ new CurrentRequest(), new MatchedRoute(), new Path('slug', 'string') ]
            ],
            'nullable request' => [ 'nullable_request', [ '/' ], [ new CurrentRequest() ] ],
            'enums' => [
                'enums',
                [ '/:status/:rank' ],
                [
                    new Path('status', StringBackedEnum::class, 'string'),
                    new Path('rank', IntBackedEnum::class, 'int'),
                ]
            ],
            'enum with default' => [
                'enum_with_default',
                [ '/:status' ],
                [ new Path('status', StringBackedEnum::class, 'string', false, true, StringBackedEnum::DRAFT) ]
            ],
            'with default' => [
                'with_default',
                [ '/:page' ],
                [ new Path('page', 'int', null, false, true, 1) ]
            ],
            'nullable' => [
                'nullable',
                [ '/:page' ],
                [ new Path('page', 'int', null, true) ]
            ],
            'nullable with default' => [
                'nullable_with_default',
                [ '/:page' ],
                [ new Path('page', 'int', null, true, true, null) ]
            ],
            'catch-all' => [
                'catch_all',
                [ '/files/*' ],
                [ new Path('all', 'string') ]
            ],
            'several routes' => [
                'path_parameter',
                [ '/articles/:id', '/legacy/:id' ],
                [ new Path('id', 'int') ]
            ],
            'optional, in some routes' => [
                'nullable_with_default',
                [ '/articles', '/articles/:page' ],
                [ new Path('page', 'int', null, true, true, null) ]
            ],
            'default, in some routes' => [
                'with_default',
                [ '/articles', '/articles/:page' ],
                [ new Path('page', 'int', null, false, true, 1) ]
            ],
            'path attribute' => [ 'path_attribute', [ '/articles/:id' ], [ new Path('id', 'int') ] ],
            'path attribute with a name' => [ 'path_renamed', [ '/articles/:id' ], [ new Path('id', 'int') ] ],
            'query' => [ 'query_parameter', [ '/' ], [ new Query('page', 'int', null, false, true, 1) ] ],
            'query with a name' => [ 'query_renamed', [ '/' ], [ new Query('q', 'string') ] ],
            'query enum and nullable' => [
                'query_enum',
                [ '/' ],
                [
                    new Query('status', StringBackedEnum::class, 'string'),
                    new Query('archived', 'bool', null, true),
                ]
            ],
            'part' => [ 'part_parameter', [ '/' ], [ new Part('title', 'string') ] ],
            'part with a name' => [ 'part_renamed', [ '/' ], [ new Part('author_name', 'string', null, true) ] ],
            'all sources' => [
                'all_sources',
                [ '/articles/:id' ],
                [
                    new CurrentRequest(),
                    new Path('id', 'int'),
                    new Query('page', 'int', null, false, true, 1),
                    new Part('title', 'string', null, false, true, ''),
                ]
            ],
            'query is not looked up in the pattern' => [
                'query_parameter',
                [ '/articles/:id' ],
                [ new Query('page', 'int', null, false, true, 1) ]
            ],
            'decoded path' => [
                'decoded_path',
                [ '/articles/:slug' ],
                [ new Decoded('path', 'slug', SlugDecoder::class, Slug::class) ]
            ],
            'decoded path with a name' => [
                'decoded_path_renamed',
                [ '/articles/:slug' ],
                [ new Decoded('path', 'slug', SlugDecoder::class, Slug::class) ]
            ],
            'decoded by its type, no attribute' => [
                'decoded_implicit_path',
                [ '/articles/:article' ],
                [ new Decoded('path', 'article', Article::class, Article::class) ]
            ],
            'decoded query' => [
                'decoded_query',
                [ '/' ],
                [ new Decoded('query', 'slug', SlugDecoder::class, Slug::class, true) ]
            ],
            'decoded part' => [
                'decoded_part',
                [ '/' ],
                [ new Decoded('part', 's', SlugDecoder::class, Slug::class) ]
            ],
            'decoded by its type, with an attribute' => [
                'decoded_self_query',
                [ '/' ],
                [ new Decoded('query', 'article', Article::class, Article::class) ]
            ],
            'decoded object' => [
                'decoded_object',
                [ '/' ],
                [ new Decoded('query', 'value', Wide::class, 'object') ]
            ],
            'decoded with a default' => [
                'decoded_with_default',
                [ '/' ],
                [ new Decoded('query', 'slug', SlugDecoder::class, Slug::class, true, true, null) ]
            ],
            'decoded query is not looked up in the pattern' => [
                'decoded_query',
                [ '/articles/:id' ],
                [ new Decoded('query', 'slug', SlugDecoder::class, Slug::class, true) ]
            ],

            'nullable, in some routes' => [
                'nullable',
                [ '/articles', '/articles/:page' ],
                [ new Path('page', 'int', null, true) ]
            ],

        ];
    }

    #[DataProvider('provide_valid_return_type')]
    public function test_valid_return_type(string $method): void
    {
        $this->assertSame([], PlanBuilder::build($this->method($method), $this->patterns([ '/' ])));
    }

    /**
     * @return array<string, array{ string }>
     */
    public static function provide_valid_return_type(): array
    {
        return [

            'response subclass' => [ 'returns_response_subclass' ],
            'into response' => [ 'returns_into_response' ],
            'into response interface' => [ 'returns_into_response_interface' ],
            'union' => [ 'returns_union' ],

        ];
    }

    public function test_static_return_type_is_resolved_against_the_declaring_class(): void
    {
        $method = new ReflectionMethod(Wrapper::class, 'again');

        $this->assertSame([], PlanBuilder::build($method, $this->patterns([ '/' ])));
    }

    #[DataProvider('provide_not_valid_method')]
    public function test_not_valid_method(string $method, string $message): void
    {
        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage($message);

        PlanBuilder::build($this->method($method), $this->patterns([ '/' ]));
    }

    /**
     * @return array<string, array{ string, string }>
     */
    public static function provide_not_valid_method(): array
    {
        $class = PlanController::class;
        $return = "must declare a return type of ICanBoogie\HTTP\Response or ICanBoogie\Routing\IntoResponse.";

        return [

            'not public' => [
                'not_public',
                "`$class::not_public()` must be a public, non-static, non-abstract method."
            ],
            'static' => [
                'is_static',
                "`$class::is_static()` must be a public, non-static, non-abstract method."
            ],
            'no return type' => [ 'no_return_type', "`$class::no_return_type()` $return" ],
            'void' => [ 'returns_void', "`$class::returns_void()` $return" ],
            'string' => [ 'returns_string', "`$class::returns_string()` $return" ],
            'mixed' => [ 'returns_mixed', "`$class::returns_mixed()` $return" ],
            'nullable' => [ 'returns_nullable', "`$class::returns_nullable()` $return" ],
            'union with null' => [ 'returns_union_with_null', "`$class::returns_union_with_null()` $return" ],
            'union with string' => [ 'returns_union_with_string', "`$class::returns_union_with_string()` $return" ],
            'unrelated class' => [ 'returns_unrelated_class', "`$class::returns_unrelated_class()` $return" ],
            'intersection' => [ 'returns_intersection', "`$class::returns_intersection()` $return" ],
            'static, of a class that is not a response' => [
                'returns_static',
                "`$class::returns_static()` $return"
            ],

        ];
    }

    #[DataProvider('provide_not_valid_parameter')]
    public function test_not_valid_parameter(string $method, array $patterns, string $message): void
    {
        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage($message);

        PlanBuilder::build($this->method($method), $this->patterns($patterns));
    }

    /**
     * @return array<string, array{ string, string[], string }>
     */
    public static function provide_not_valid_parameter(): array
    {
        $class = PlanController::class;
        $expected = "expected int, float, string, bool, a backed enum, ICanBoogie\HTTP\Request, "
            . "or ICanBoogie\Routing\Route.";

        return [

            'no route' => [
                'no_parameters',
                [],
                "`$class::no_parameters()` has no route."
            ],
            'untyped' => [
                'untyped',
                [ '/:x' ],
                "`$class::untyped()` parameter `\$x`: the type is missing, $expected"
            ],
            'union type' => [
                'union_type',
                [ '/:x' ],
                "`$class::union_type()` parameter `\$x`: the type `string|int` is not supported, $expected"
            ],
            'array' => [
                'array_type',
                [ '/:x' ],
                "`$class::array_type()` parameter `\$x`: the type `array` is not supported, $expected"
            ],
            'mixed' => [
                'mixed_type',
                [ '/:x' ],
                "`$class::mixed_type()` parameter `\$x`: the type `mixed` is not supported, $expected"
            ],
            'unrelated class' => [
                'unrelated_class',
                [ '/:x' ],
                "`$class::unrelated_class()` parameter `\$x`: the type `stdClass` is not supported, $expected"
            ],
            'unit enum' => [
                'unit_enum',
                [ '/:x' ],
                "`$class::unit_enum()` parameter `\$x`: the type "
                . "`Test\ICanBoogie\Routing\PlanBuilder\PlainEnum` is not supported, $expected"
            ],
            'variadic' => [
                'variadic',
                [ '/:ids' ],
                "`$class::variadic()` parameter `\$ids`: variadic parameters are not supported."
            ],
            'two sources' => [
                'two_sources',
                [ '/:x' ],
                "`$class::two_sources()` parameter `\$x`: a parameter is bound to a single source, found "
                . "#[ICanBoogie\Routing\Attribute\Query], #[ICanBoogie\Routing\Attribute\Part]."
            ],
            'path and query' => [
                'path_and_query',
                [ '/:x' ],
                "`$class::path_and_query()` parameter `\$x`: a parameter is bound to a single source, found "
                . "#[ICanBoogie\Routing\Attribute\Path], #[ICanBoogie\Routing\Attribute\Query]."
            ],
            'attribute on the request' => [
                'attribute_on_request',
                [ '/' ],
                "`$class::attribute_on_request()` parameter `\$request`: the parameter receives the request, "
                . "it cannot have the attribute #[ICanBoogie\Routing\Attribute\Query]."
            ],
            'attribute on the route' => [
                'attribute_on_route',
                [ '/' ],
                "`$class::attribute_on_route()` parameter `\$route`: the parameter receives the route, "
                . "it cannot have the attribute #[ICanBoogie\Routing\Attribute\Part]."
            ],
            'decoder is not a decoder' => [
                'decoded_not_a_decoder',
                [ '/' ],
                "`$class::decoded_not_a_decoder()` parameter `\$slug`: `Test\\ICanBoogie\\Routing\\Decoder\\NotADecoder` "
                . "is not a decoder, it must implement ICanBoogie\\Routing\\Decoder."
            ],
            'decoder is not a class' => [
                'decoded_not_a_class',
                [ '/' ],
                "`$class::decoded_not_a_class()` parameter `\$slug`: `Nope\\Missing` is not a decoder"
            ],
            'decoder does not narrow its return type' => [
                'decoded_wide',
                [ '/' ],
                "`$class::decoded_wide()` parameter `\$slug`: `Test\\ICanBoogie\\Routing\\Decoder\\Wide::decode()` "
                . "returns `object`, which is not assignable to `Test\\ICanBoogie\\Routing\\Decoder\\Slug`"
            ],
            'decoder returns another type' => [
                'decoded_unrelated',
                [ '/' ],
                "`$class::decoded_unrelated()` parameter `\$slug`: "
                . "`Test\\ICanBoogie\\Routing\\Decoder\\Unrelated::decode()` returns `stdClass`, which is not assignable to"
            ],
            'decoded scalar' => [
                'decoded_scalar',
                [ '/' ],
                "`$class::decoded_scalar()` parameter `\$slug`: the type `string` cannot be decoded"
            ],
            'decoded union' => [
                'decoded_union',
                [ '/' ],
                "`$class::decoded_union()` parameter `\$slug`: the type `Test\\ICanBoogie\\Routing\\Decoder\\Slug|"
                . "Test\\ICanBoogie\\Routing\\Decoder\\Article` cannot be decoded"
            ],
            'decoded default that cannot be exported' => [
                'decoded_object_default',
                [ '/' ],
                "`$class::decoded_object_default()` parameter `\$slug`: the default value cannot be exported"
            ],
            'decoded path not in the pattern' => [
                'decoded_missing_placeholder',
                [ '/articles/:id' ],
                "`$class::decoded_missing_placeholder()` parameter `\$slug`: no `:slug` in `/articles/:id`."
            ],
            'class without a decoder points to decoders' => [
                'unrelated_class',
                [ '/:x' ],
                "A class needs a decoder, see `ICanBoogie\\Routing\\Decoder`."
            ],

            'query array' => [
                'query_array',
                [ '/' ],
                "`$class::query_array()` parameter `\$tags`: the type `array` is not supported, $expected"
            ],
            'part class' => [
                'part_class',
                [ '/' ],
                "`$class::part_class()` parameter `\$payload`: the type `stdClass` is not supported, $expected"
            ],
            'renamed placeholder is not in the pattern' => [
                'path_renamed_missing',
                [ '/articles/:id' ],
                "`$class::path_renamed_missing()` parameter `\$id`: no `:slug` in `/articles/:id`."
            ],
            'not in the pattern' => [
                'path_parameter',
                [ '/skills/:slug' ],
                "`$class::path_parameter()` parameter `\$id`: no `:id` in `/skills/:slug`."
            ],
            'anonymous placeholder' => [
                'path_parameter',
                [ '/articles/<\d+>' ],
                "`$class::path_parameter()` parameter `\$id`: no `:id` in `/articles/<\d+>`."
            ],
            'not in any of the patterns' => [
                'path_parameter',
                [ '/a/:slug', '/b/:slug' ],
                "`$class::path_parameter()` parameter `\$id`: no `:id` in any of `/a/:slug`, `/b/:slug`."
            ],
            'not in one of the patterns' => [
                'path_parameter',
                [ '/articles/:id', '/articles' ],
                "`$class::path_parameter()` parameter `\$id`: no `:id` in `/articles`, "
                . "add a default value or make the parameter nullable if it's optional."
            ],
            'not in some of the patterns' => [
                'path_parameter',
                [ '/a', '/b/:id', '/c' ],
                "`$class::path_parameter()` parameter `\$id`: no `:id` in any of `/a`, `/c`, "
                . "add a default value or make the parameter nullable if it's optional."
            ],
            'optional but never in the patterns' => [
                'nullable_with_default',
                [ '/articles' ],
                "`$class::nullable_with_default()` parameter `\$page`: no `:page` in `/articles`."
            ],
            'default but never in the patterns' => [
                'with_default',
                [ '/a', '/b' ],
                "`$class::with_default()` parameter `\$page`: no `:page` in any of `/a`, `/b`."
            ],

        ];
    }

    /**
     * @param string[] $patterns
     *
     * @return list<Pattern>
     */
    private function patterns(array $patterns): array
    {
        return array_map(Pattern::from(...), $patterns);
    }

    private function method(string $name): ReflectionMethod
    {
        return new ReflectionMethod(PlanController::class, $name);
    }
}
