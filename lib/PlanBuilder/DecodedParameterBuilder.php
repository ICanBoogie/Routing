<?php

namespace ICanBoogie\Routing\PlanBuilder;

use ICanBoogie\Routing\Attribute;
use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\Parameter\Decoded;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

use function class_exists;
use function is_a;
use function is_array;
use function is_scalar;
use function is_subclass_of;

/**
 * Builds the {@see Decoded} parameter of a parameter that has a {@see Decoder}, after checking that
 * the decoder can provide the type of the parameter.
 *
 * @internal
 */
final readonly class DecodedParameterBuilder
{
    /**
     * Builds the {@see Decoded} parameter of a parameter that has a decoder.
     *
     * @return Decoded|null
     *     The parameter, or `null` if the parameter doesn't have a decoder.
     *
     * @throws ConfigNotValid
     */
    public static function try_build_decoded_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        Attribute\Path|Attribute\Query|Attribute\Part|Attribute\Body|null $attribute
    ): ?Decoded {
        $decoder = self::find_decoder($method, $parameter, $attribute);

        return $decoder
            ? self::build_decoded_parameter($method, $parameter, $attribute, $decoder)
            : null;
    }

    /**
     * Finds the decoder of a parameter: the one named by `using`, otherwise the type of the
     * parameter if it is a decoder itself.
     *
     * @return class-string<Decoder>|null
     *
     * @throws ConfigNotValid if `using` is not a decoder.
     */
    private static function find_decoder(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        Attribute\Path|Attribute\Query|Attribute\Part|Attribute\Body|null $attribute
    ): ?string {
        $using = $attribute?->using;

        if ($using !== null) {
            if (!class_exists($using) || !is_subclass_of($using, Decoder::class)) {
                throw ConfigNotValid::for_parameter(
                    $method,
                    $parameter,
                    "`$using` is not a decoder, it must implement " . Decoder::class . "."
                );
            }

            return $using;
        }

        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
            && is_subclass_of($type->getName(), Decoder::class)
                ? $type->getName()
                : null;
    }

    /**
     * The return type of the decoder must be a class assignable to the type of the parameter, or
     * `object`, for a decoder that serves many types, using {@see EncodedValue::$into_class}. A
     * decoder returning `object` is checked when it is called.
     *
     * @param class-string<Decoder> $decoder
     *
     * @throws ConfigNotValid
     */
    private static function assert_decoder_returns(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        string $decoder,
        ReflectionNamedType $expected
    ): void {
        $returned = new ReflectionClass($decoder)->getMethod('decode')->getReturnType();
        $returned_name = $returned instanceof ReflectionNamedType ? $returned->getName() : null;

        if ($returned_name === 'self' || $returned_name === 'static') {
            $returned_name = $decoder;
        }

        $expected_name = $expected->getName();

        if (
            !$returned_name
            || $returned->isBuiltin() && $returned_name !== 'object'
            || $returned_name !== 'object' && $expected_name !== 'object'
                && !is_a($returned_name, $expected_name, true)
        ) {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                "`$decoder::decode()` returns `" . ($returned ?? 'mixed') . "`, which is not assignable to "
                . "`$expected_name`, declare a return type that is, or `object`."
            );
        }
    }

    /**
     * @param class-string<Decoder> $decoder
     *
     * @throws ConfigNotValid
     */
    private static function build_decoded_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        Attribute\Path|Attribute\Query|Attribute\Part|Attribute\Body|null $attribute,
        string $decoder
    ): Decoded {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin() && $type->getName() !== 'object') {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                "the type `$type` cannot be decoded, expected a class or `object`, optionally nullable."
            );
        }

        self::assert_decoder_returns($method, $parameter, $decoder, $type);

        $source = match (true) {
            $attribute instanceof Attribute\Query => 'query',
            $attribute instanceof Attribute\Part => 'part',
            $attribute instanceof Attribute\Body => 'body',
            default => 'path',
        };

        $name = ($attribute instanceof Attribute\Body ? null : $attribute?->name) ?? $parameter->getName();
        $nullable = $parameter->allowsNull();
        $has_default = $parameter->isDefaultValueAvailable();
        $default = $has_default ? $parameter->getDefaultValue() : null;

        if ($has_default && !self::is_exportable($default)) {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                "the default value cannot be exported, use null, a scalar, an array, or an enum case; "
                . "make the parameter nullable to apply a default in the action."
            );
        }

        return new Decoded(
            source: $source,
            name: $name,
            decoder: $decoder,
            into_class: $type->getName(),
            nullable: $nullable,
            has_default: $has_default,
            default: $default,
        );
    }

    /**
     * Whether the value can be written by `var_export()` and read back: `null`, a scalar, an enum
     * case, or an array of those.
     */
    private static function is_exportable(mixed $value): bool
    {
        if ($value === null || is_scalar($value) || $value instanceof \UnitEnum) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!self::is_exportable($item)) {
                return false;
            }
        }

        return true;
    }
}
