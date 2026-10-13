<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\ParameterAbstract;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(Path::class)]
#[CoversClass(ParameterAbstract::class)]
#[CoversClass(PathParameterNotValid::class)]
final class PathTest extends TestCase
{
    public function test_resolve_casts_to_the_type(): void
    {
        $parameter = new Path('id', 'int');

        $this->assertSame(123, $parameter->resolve($this->request([ 'id' => '123' ])));
    }

    public function test_resolve_uses_the_name_not_the_position(): void
    {
        $parameter = new Path('year', 'int');

        $this->assertSame(2026, $parameter->resolve($this->request([ 'slug' => 'hello', 'year' => '2026' ])));
    }

    public function test_resolve_enum(): void
    {
        $parameter = new Path('status', StringBackedEnum::class, 'string');

        $this->assertSame(
            StringBackedEnum::PUBLISHED,
            $parameter->resolve($this->request([ 'status' => 'published' ]))
        );
    }

    public function test_resolve_int_backed_enum(): void
    {
        $parameter = new Path('rank', IntBackedEnum::class, 'int');

        $this->assertSame(IntBackedEnum::TWO, $parameter->resolve($this->request([ 'rank' => '2' ])));
    }

    public function test_not_valid_is_not_found(): void
    {
        $parameter = new Path('id', 'int');

        $this->expectException(PathParameterNotValid::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage("The path parameter 'id' is not a valid int.");

        $parameter->resolve($this->request([ 'id' => 'abc' ]));
    }

    public function test_value_that_is_not_a_string_is_not_found(): void
    {
        $parameter = new Path('id', 'int');

        $this->expectException(PathParameterNotValid::class);

        $parameter->resolve($this->request([ 'id' => 123 ]));
    }

    public function test_not_valid_enum_is_not_found(): void
    {
        $parameter = new Path('status', StringBackedEnum::class, 'string');

        $this->expectException(PathParameterNotValid::class);

        $parameter->resolve($this->request([ 'status' => 'archived' ]));
    }

    public function test_absent_uses_the_default(): void
    {
        $parameter = new Path('page', 'int', has_default: true, default: 1);

        $this->assertSame(1, $parameter->resolve($this->request([])));
    }

    public function test_absent_uses_null_default(): void
    {
        $parameter = new Path('page', 'int', nullable: true, has_default: true, default: null);

        $this->assertNull($parameter->resolve($this->request([])));
    }

    public function test_absent_nullable_without_default_is_null(): void
    {
        $parameter = new Path('page', 'int', nullable: true);

        $this->assertNull($parameter->resolve($this->request([])));
    }

    public function test_absent_default_is_not_used_when_present(): void
    {
        $parameter = new Path('page', 'int', has_default: true, default: 1);

        $this->assertSame(3, $parameter->resolve($this->request([ 'page' => '3' ])));
    }

    public function test_absent_required_is_a_wiring_mistake(): void
    {
        $parameter = new Path('id', 'int');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The path parameter 'id' is not available.");

        $parameter->resolve($this->request([]));
    }

    public function test_export(): void
    {
        $parameter = new Path('status', StringBackedEnum::class, 'string', true, true, StringBackedEnum::DRAFT);
        $actual = SetStateHelper::export_import($parameter);

        $this->assertEquals($parameter, $actual);
        $this->assertSame(StringBackedEnum::DRAFT, $actual->default);
    }

    /**
     * @param array<string, string|int> $path_params
     */
    private function request(array $path_params): Request
    {
        return Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_PATH_PARAMS => $path_params,
        ]);
    }
}
