<?php

namespace Test\ICanBoogie\Routing\Parameter;

use BackedEnum;
use ICanBoogie\Routing\Parameter\Casting;
use ICanBoogie\Routing\Parameter\NotCastable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(Casting::class)]
final class CastingTest extends TestCase
{
    /**
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     */
    #[DataProvider('provide_cast')]
    public function test_cast(string $type, ?string $backing, string $value, mixed $expected): void
    {
        $this->assertSame($expected, Casting::cast($type, $backing, $value));
    }

    /**
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     */
    #[DataProvider('provide_cast_input')]
    public function test_cast_input(string $type, ?string $backing, mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, Casting::cast_input($type, $backing, $value));
    }

    /**
     * @return array<string, array{ string, string|null, mixed, mixed }>
     */
    public static function provide_cast_input(): array
    {
        return [

            'string is cast' => [ 'int', null, '123', 123 ],
            'int' => [ 'int', null, 123, 123 ],
            'float' => [ 'float', null, 1.5, 1.5 ],
            'int as float' => [ 'float', null, 2, 2.0 ],
            'bool' => [ 'bool', null, false, false ],
            'int native' => [ 'int', null, 1, 1 ],
            'int enum' => [ IntBackedEnum::class, 'int', 2, IntBackedEnum::TWO ],
            'string enum' => [ StringBackedEnum::class, 'string', 'draft', StringBackedEnum::DRAFT ],

        ];
    }

    /**
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     */
    #[DataProvider('provide_cast_input_not_valid')]
    public function test_cast_input_not_valid(string $type, ?string $backing, mixed $value): void
    {
        $this->expectException(NotCastable::class);

        Casting::cast_input($type, $backing, $value);
    }

    /**
     * @return array<string, array{ string, string|null, mixed }>
     */
    public static function provide_cast_input_not_valid(): array
    {
        return [

            'int as float string' => [ 'int', null, 1.5 ],
            'int as bool' => [ 'int', null, true ],
            'float infinite' => [ 'float', null, INF ],
            'float as bool' => [ 'float', null, true ],
            'bool as int' => [ 'bool', null, 1 ],
            'string as int' => [ 'string', null, 123 ],
            'array' => [ 'string', null, [ 'a' ] ],
            'string enum with an int' => [ StringBackedEnum::class, 'string', 1 ],
            'int enum with an unknown case' => [ IntBackedEnum::class, 'int', 99 ],

        ];
    }

    /**
     * @return array<string, array{ string, string|null, string, mixed }>
     */
    public static function provide_cast(): array
    {
        return [

            'string' => [ 'string', null, 'abc', 'abc' ],
            'empty string' => [ 'string', null, '', '' ],

            'int' => [ 'int', null, '123', 123 ],
            'int zero' => [ 'int', null, '0', 0 ],
            'int negative' => [ 'int', null, '-123', -123 ],
            'int with leading zeros' => [ 'int', null, '007', 7 ],
            'int zeros only' => [ 'int', null, '000', 0 ],
            'int negative zero' => [ 'int', null, '-0', 0 ],
            'int max' => [ 'int', null, (string) PHP_INT_MAX, PHP_INT_MAX ],
            'int min' => [ 'int', null, (string) PHP_INT_MIN, PHP_INT_MIN ],

            'float' => [ 'float', null, '1.5', 1.5 ],
            'float integer' => [ 'float', null, '2', 2.0 ],
            'float negative' => [ 'float', null, '-0.25', -0.25 ],
            'float exponent' => [ 'float', null, '1e3', 1000.0 ],
            'float leading dot' => [ 'float', null, '.5', 0.5 ],

            'bool 1' => [ 'bool', null, '1', true ],
            'bool true' => [ 'bool', null, 'true', true ],
            'bool 0' => [ 'bool', null, '0', false ],
            'bool false' => [ 'bool', null, 'false', false ],

            'int backed enum' => [ IntBackedEnum::class, 'int', '2', IntBackedEnum::TWO ],
            'int backed enum, leading zeros' => [ IntBackedEnum::class, 'int', '01', IntBackedEnum::ONE ],
            'string backed enum' => [ StringBackedEnum::class, 'string', 'draft', StringBackedEnum::DRAFT ],

        ];
    }

    /**
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     */
    #[DataProvider('provide_not_castable')]
    public function test_not_castable(string $type, ?string $backing, string $value): void
    {
        $this->expectException(NotCastable::class);

        Casting::cast($type, $backing, $value);
    }

    /**
     * @return array<string, array{ string, string|null, string }>
     */
    public static function provide_not_castable(): array
    {
        return [

            'int, letters' => [ 'int', null, 'abc' ],
            'int, empty' => [ 'int', null, '' ],
            'int, decimal' => [ 'int', null, '1.5' ],
            'int, plus sign' => [ 'int', null, '+1' ],
            'int, spaces' => [ 'int', null, ' 1' ],
            'int, trailing letters' => [ 'int', null, '1a' ],
            'int, too large' => [ 'int', null, PHP_INT_MAX . '0' ],
            'int, too small' => [ 'int', null, PHP_INT_MIN . '0' ],

            'float, letters' => [ 'float', null, 'abc' ],
            'float, empty' => [ 'float', null, '' ],
            'float, spaces' => [ 'float', null, ' 1.5' ],
            'float, not finite' => [ 'float', null, '1e999' ],
            'float, INF' => [ 'float', null, 'INF' ],
            'float, NAN' => [ 'float', null, 'NAN' ],

            'bool, yes' => [ 'bool', null, 'yes' ],
            'bool, uppercase' => [ 'bool', null, 'TRUE' ],
            'bool, empty' => [ 'bool', null, '' ],

            'int backed enum, unknown case' => [ IntBackedEnum::class, 'int', '3' ],
            'int backed enum, not an integer' => [ IntBackedEnum::class, 'int', 'one' ],
            'string backed enum, unknown case' => [ StringBackedEnum::class, 'string', 'archived' ],
            'string backed enum, wrong case' => [ StringBackedEnum::class, 'string', 'DRAFT' ],

        ];
    }
}
