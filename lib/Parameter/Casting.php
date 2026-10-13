<?php

namespace ICanBoogie\Routing\Parameter;

use BackedEnum;

use function filter_var;
use function is_finite;
use function is_numeric;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function substr;
use function trim;

use const FILTER_VALIDATE_INT;

/**
 * Casts the strings found in a request to the type of a parameter.
 *
 * The casting is explicit, it is not PHP's coercion of arguments, and it doesn't depend on
 * `strict_types`.
 *
 * @internal
 */
final class Casting
{
    /**
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     *     The type backing the enum, when `$type` is an enum.
     *
     * @throws NotCastable
     */
    public static function cast(string $type, ?string $backing, string $value): mixed
    {
        return match ($type) {
            'string' => $value,
            'int' => self::to_int($value),
            'float' => self::to_float($value),
            'bool' => self::to_bool($value),
            default => self::to_enum($type, $backing, $value),
        };
    }

    /**
     * An optional `-` followed by decimal digits, within the integer range. Leading zeros are
     * accepted, which `FILTER_VALIDATE_INT` doesn't do, so they are removed before the range is
     * checked.
     *
     * @throws NotCastable
     */
    private static function to_int(string $value): int
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new NotCastable("Not an integer: '$value'");
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($negative ? substr($value, 1) : $value, '0') ?: '0';
        $int = filter_var($negative && $digits !== '0' ? "-$digits" : $digits, FILTER_VALIDATE_INT);

        if ($int === false) {
            throw new NotCastable("Integer out of range: '$value'");
        }

        return $int;
    }

    /**
     * @throws NotCastable
     */
    private static function to_float(string $value): float
    {
        if (!is_numeric($value) || trim($value) !== $value || !is_finite((float) $value)) {
            throw new NotCastable("Not a finite float: '$value'");
        }

        return (float) $value;
    }

    /**
     * @throws NotCastable
     */
    private static function to_bool(string $value): bool
    {
        return match ($value) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => throw new NotCastable("Not a boolean: '$value'"),
        };
    }

    /**
     * @param class-string<BackedEnum> $enum
     * @param 'int'|'string'|null $backing
     *
     * @throws NotCastable
     */
    private static function to_enum(string $enum, ?string $backing, string $value): BackedEnum
    {
        $case = $enum::tryFrom($backing === 'int' ? self::to_int($value) : $value);

        return $case ?? throw new NotCastable("Not a case of $enum: '$value'");
    }
}
