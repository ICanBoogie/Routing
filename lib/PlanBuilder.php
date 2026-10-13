<?php

namespace ICanBoogie\Routing;

use BackedEnum;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Parameter\Part;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\Parameter\Query;
use ICanBoogie\Routing\PlanBuilder\DecodedParameterBuilder;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

use function array_map;
use function class_exists;
use function count;
use function enum_exists;
use function implode;
use function in_array;
use function is_a;
use function is_subclass_of;
use function sprintf;

/**
 * Builds the plan of an action's method: the list of parameters that produce its arguments.
 *
 * The method is analyzed once, when the configuration is built, so that nothing is reflected when
 * requests are handled. Mistakes are reported right away, with the name of the class, the method,
 * and the parameter.
 *
 * Each parameter is claimed by the first rule that applies:
 *
 * - A parameter typed {@see Request} receives the request.
 * - A parameter typed {@see Route} receives the route that matched the request.
 * - A parameter with a {@see Decoder}, from the `using` argument of its attribute or because its
 *   type is a decoder itself, receives the object the decoder makes from the value.
 * - A parameter with the attribute {@see Attribute\Query} or {@see Attribute\Part} receives a value
 *   of the query string or of the body, cast to its type.
 * - Any other parameter is a path parameter, found by its name in the pattern of the route, or by
 *   the name of {@see Attribute\Path}, and cast to its type: `int`, `float`, `string`, `bool`, or a
 *   backed enum.
 */
final readonly class PlanBuilder
{
    private const array SCALARS = [ 'int', 'float', 'string', 'bool' ];

    /**
     * @param list<Pattern> $patterns
     *     The patterns of all the routes that reach the method. A path parameter must be a
     *     placeholder of every pattern, unless it is nullable or has a default value.
     *
     * @return list<Parameter>
     *     The parameters of the method, in order.
     *
     * @throws ConfigNotValid
     */
    public static function build(ReflectionMethod $method, array $patterns): array
    {
        if (!$patterns) {
            throw ConfigNotValid::for_method($method, "has no route.");
        }

        self::assert_invocable($method);
        self::assert_returns_response($method);

        $plan = [];

        foreach ($method->getParameters() as $parameter) {
            $plan[] = self::build_parameter($method, $parameter, $patterns);
        }

        return $plan;
    }

    /**
     * @throws ConfigNotValid
     */
    private static function assert_invocable(ReflectionMethod $method): void
    {
        if (!$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
            throw ConfigNotValid::for_method($method, "must be a public, non-static, non-abstract method.");
        }
    }

    /**
     * The method must return a {@see Response} or an {@see IntoResponse}, and nothing else.
     *
     * @throws ConfigNotValid
     */
    private static function assert_returns_response(ReflectionMethod $method): void
    {
        $type = $method->getReturnType();
        $reason = "must declare a return type of " . Response::class . " or " . IntoResponse::class . ".";

        if (!$type || $type->allowsNull()) {
            throw ConfigNotValid::for_method($method, $reason);
        }

        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [ $type ] as $named_type) {
            if (!$named_type instanceof ReflectionNamedType || $named_type->isBuiltin()) {
                throw ConfigNotValid::for_method($method, $reason);
            }

            $name = $named_type->getName();

            if ($name === 'self' || $name === 'static') {
                $name = $method->getDeclaringClass()->name;
            }

            if (!is_a($name, Response::class, true) && !is_a($name, IntoResponse::class, true)) {
                throw ConfigNotValid::for_method($method, $reason);
            }
        }
    }

    /**
     * @param list<Pattern> $patterns
     *
     * @throws ConfigNotValid
     */
    private static function build_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        array $patterns
    ): Parameter {
        if ($parameter->isVariadic()) {
            throw ConfigNotValid::for_parameter($method, $parameter, "variadic parameters are not supported.");
        }

        $attribute = self::find_source_attribute($method, $parameter);
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $claimed = match ($type->getName()) {
                Request::class => new CurrentRequest(),
                Route::class => new MatchedRoute(),
                default => null,
            };

            if ($claimed) {
                return $attribute
                    ? throw ConfigNotValid::for_parameter(
                        $method,
                        $parameter,
                        "the parameter receives the " . ($claimed instanceof CurrentRequest ? "request" : "route")
                        . ", it cannot have the attribute #[" . $attribute::class . "]."
                    )
                    : $claimed;
            }
        }

        $decoded = DecodedParameterBuilder::try_build_decoded_parameter($method, $parameter, $attribute);

        if ($decoded) {
            if ($decoded->source === 'path') {
                self::assert_in_patterns(
                    $method,
                    $parameter,
                    $decoded->name,
                    $patterns,
                    $decoded->nullable || $decoded->has_default
                );
            }

            return $decoded;
        }

        return match (true) {
            $attribute instanceof Attribute\Query => self::build_input_parameter(
                $method,
                $parameter,
                $attribute->name,
                Query::class
            ),
            $attribute instanceof Attribute\Part => self::build_input_parameter(
                $method,
                $parameter,
                $attribute->name,
                Part::class
            ),
            default => self::build_path_parameter($method, $parameter, $patterns, $attribute?->name),
        };
    }

    /**
     * @throws ConfigNotValid if the parameter has more than one attribute that binds it to a source.
     */
    private static function find_source_attribute(
        ReflectionMethod $method,
        ReflectionParameter $parameter
    ): Attribute\Path|Attribute\Query|Attribute\Part|null {
        $attributes = [
            ...$parameter->getAttributes(Attribute\Path::class),
            ...$parameter->getAttributes(Attribute\Query::class),
            ...$parameter->getAttributes(Attribute\Part::class),
        ];

        if (count($attributes) > 1) {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                "a parameter is bound to a single source, found "
                . implode(', ', array_map(fn($a) => "#[" . $a->getName() . "]", $attributes)) . "."
            );
        }

        return $attributes ? $attributes[0]->newInstance() : null;
    }

    /**
     * @param class-string<Query>|class-string<Part> $class
     * @param non-empty-string|null $name
     *     Name of the value in the source, defaults to the name of the parameter.
     *
     * @throws ConfigNotValid
     */
    private static function build_input_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        ?string $name,
        string $class
    ): Query|Part {
        [ $type, $backing ] = self::resolve_path_type($method, $parameter);

        $has_default = $parameter->isDefaultValueAvailable();

        return new $class(
            name: $name ?? $parameter->getName(),
            type: $type,
            backing: $backing,
            nullable: $parameter->allowsNull(),
            has_default: $has_default,
            default: $has_default ? $parameter->getDefaultValue() : null,
        );
    }

    /**
     * @param list<Pattern> $patterns
     * @param non-empty-string|null $name
     *     Name of the placeholder, defaults to the name of the parameter.
     *
     * @throws ConfigNotValid
     */
    private static function build_path_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        array $patterns,
        ?string $name
    ): Path {
        [ $type, $backing ] = self::resolve_path_type($method, $parameter);

        $name ??= $parameter->getName();
        $nullable = $parameter->allowsNull();
        $has_default = $parameter->isDefaultValueAvailable();

        self::assert_in_patterns($method, $parameter, $name, $patterns, $nullable || $has_default);

        return new Path(
            name: $name,
            type: $type,
            backing: $backing,
            nullable: $nullable,
            has_default: $has_default,
            default: $has_default ? $parameter->getDefaultValue() : null,
        );
    }

    /**
     * @return array{ 'int'|'float'|'string'|'bool'|class-string<BackedEnum>, 'int'|'string'|null }
     *     The type, and the type backing the enum if the type is an enum.
     *
     * @throws ConfigNotValid
     */
    private static function resolve_path_type(ReflectionMethod $method, ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $expected = "expected " . implode(', ', self::SCALARS) . ", a backed enum, "
            . Request::class . ", or " . Route::class . ".";

        if (!$type) {
            throw ConfigNotValid::for_parameter($method, $parameter, "the type is missing, $expected");
        }

        if (!$type instanceof ReflectionNamedType) {
            throw ConfigNotValid::for_parameter($method, $parameter, "the type `$type` is not supported, $expected");
        }

        $name = $type->getName();

        if (enum_exists($name) && is_subclass_of($name, BackedEnum::class)) {
            $backing = new ReflectionEnum($name)->getBackingType()?->getName();

            return [ $name, $backing === 'int' ? 'int' : 'string' ];
        }

        return match ($name) {
            'int' => [ 'int', null ],
            'float' => [ 'float', null ],
            'string' => [ 'string', null ],
            'bool' => [ 'bool', null ],
            default => throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                "the type `$name` is not supported, $expected"
                . (class_exists($name) ? " A class needs a decoder, see `" . Decoder::class . "`." : "")
            ),
        };
    }

    /**
     * A path parameter must be a placeholder of the patterns. It must be one of at least one
     * pattern, otherwise it would never be bound, and one of every pattern unless the parameter has
     * a fallback.
     *
     * @param non-empty-string $name
     *     Name of the placeholder.
     * @param list<Pattern> $patterns
     * @param bool $has_fallback
     *     Whether the parameter is nullable or has a default value.
     *
     * @throws ConfigNotValid
     */
    private static function assert_in_patterns(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        string $name,
        array $patterns,
        bool $has_fallback
    ): void {
        $missing = [];

        foreach ($patterns as $pattern) {
            if (!in_array($name, $pattern->params, true)) {
                $missing[] = $pattern;
            }
        }

        if (count($missing) === count($patterns)) {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                sprintf("no `:%s` in %s.", $name, self::format_patterns($patterns))
            );
        }

        if ($missing && !$has_fallback) {
            throw ConfigNotValid::for_parameter(
                $method,
                $parameter,
                sprintf(
                    "no `:%s` in %s, add a default value or make the parameter nullable if it's optional.",
                    $name,
                    self::format_patterns($missing)
                )
            );
        }
    }

    /**
     * @param list<Pattern> $patterns
     */
    private static function format_patterns(array $patterns): string
    {
        $formatted = implode(', ', array_map(fn(Pattern $p) => "`$p`", $patterns));

        return count($patterns) === 1 ? $formatted : "any of $formatted";
    }
}
