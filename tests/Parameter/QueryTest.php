<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\ParameterAbstract;
use ICanBoogie\Routing\Parameter\Casting;
use ICanBoogie\Routing\Parameter\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(Query::class)]
#[CoversClass(ParameterAbstract::class)]
#[CoversClass(ParameterNotValid::class)]
#[UsesClass(Casting::class)]
final class QueryTest extends TestCase
{
    public function test_resolve_casts_to_the_type(): void
    {
        $parameter = new Query('page', 'int');

        $this->assertSame(3, $parameter->resolve($this->request([ 'page' => '3' ])));
    }

    public function test_resolve_uses_the_name(): void
    {
        $parameter = new Query('q', 'string');

        $this->assertSame('hello', $parameter->resolve($this->request([ 'page' => '3', 'q' => 'hello' ])));
    }

    public function test_resolve_enum(): void
    {
        $parameter = new Query('status', StringBackedEnum::class, 'string');

        $this->assertSame(
            StringBackedEnum::PUBLISHED,
            $parameter->resolve($this->request([ 'status' => 'published' ]))
        );
    }

    public function test_resolve_int_backed_enum(): void
    {
        $parameter = new Query('rank', IntBackedEnum::class, 'int');

        $this->assertSame(IntBackedEnum::TWO, $parameter->resolve($this->request([ 'rank' => '2' ])));
    }

    public function test_not_valid_is_a_bad_request(): void
    {
        $parameter = new Query('page', 'int');

        try {
            $parameter->resolve($this->request([ 'page' => 'abc' ]));
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('query', $e->source);
            $this->assertSame('page', $e->name);
            $this->assertSame('int', $e->type);
            $this->assertFalse($e->missing);
            $this->assertSame("The query parameter 'page' is not a valid int.", $e->getMessage());
        }
    }

    public function test_array_is_not_valid(): void
    {
        $parameter = new Query('tags', 'string');

        $this->expectException(ParameterNotValid::class);

        $parameter->resolve($this->request([ 'tags' => [ 'a', 'b' ] ]));
    }

    public function test_missing_is_a_bad_request(): void
    {
        $parameter = new Query('page', 'int');

        try {
            $parameter->resolve($this->request([]));
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertTrue($e->missing);
            $this->assertSame("The query parameter 'page' is required.", $e->getMessage());
        }
    }

    public function test_null_is_missing(): void
    {
        $parameter = new Query('page', 'int');

        $this->expectException(ParameterNotValid::class);

        $parameter->resolve($this->request([ 'page' => null ]));
    }

    public function test_missing_uses_the_default(): void
    {
        $parameter = new Query('page', 'int', has_default: true, default: 1);

        $this->assertSame(1, $parameter->resolve($this->request([])));
    }

    public function test_missing_uses_null_default(): void
    {
        $parameter = new Query('page', 'int', nullable: true, has_default: true, default: null);

        $this->assertNull($parameter->resolve($this->request([])));
    }

    public function test_missing_nullable_is_null(): void
    {
        $parameter = new Query('page', 'int', nullable: true);

        $this->assertNull($parameter->resolve($this->request([])));
    }

    public function test_default_is_not_used_when_present(): void
    {
        $parameter = new Query('page', 'int', has_default: true, default: 1);

        $this->assertSame(3, $parameter->resolve($this->request([ 'page' => '3' ])));
    }

    public function test_empty_string_is_a_value(): void
    {
        $this->assertSame('', new Query('q', 'string', nullable: true)->resolve($this->request([ 'q' => '' ])));

        $this->expectException(ParameterNotValid::class);

        new Query('page', 'int', nullable: true)->resolve($this->request([ 'page' => '' ]));
    }

    public function test_path_params_are_ignored(): void
    {
        $parameter = new Query('id', 'int', nullable: true);
        $request = Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_PATH_PARAMS => [ 'id' => '1' ],
        ]);

        $this->assertNull($parameter->resolve($request));
    }

    public function test_array_casts_the_elements(): void
    {
        $parameter = new Query('ids', 'int', array: true);

        $actual = $parameter->resolve($this->request([ 'ids' => [ '1', '2' ] ]));

        $this->assertSame([ 1, 2 ], $actual);
    }

    public function test_array_is_not_valid_when_an_element_cannot_be_cast(): void
    {
        $parameter = new Query('ids', 'int', array: true);

        $this->expectException(ParameterNotValid::class);

        $parameter->resolve($this->request([ 'ids' => [ '1', 'x' ] ]));
    }

    public function test_export(): void
    {
        $parameter = new Query('status', StringBackedEnum::class, 'string', true, true, StringBackedEnum::DRAFT);
        $actual = SetStateHelper::export_import($parameter);

        $this->assertEquals($parameter, $actual);
        $this->assertSame(StringBackedEnum::DRAFT, $actual->default);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function request(array $values): Request
    {
        return Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_QUERY_PARAMS => $values,
        ]);
    }
}
