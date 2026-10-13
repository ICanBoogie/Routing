<?php

namespace ICanBoogie\Routing;

use BackedEnum;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Parameter\Path;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

use function array_map;
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
 * - Any other parameter is a path parameter, found by its name in the pattern of the route, and
 *   cast to its type: `int`, `float`, `string`, `bool`, or a backed enum.
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
            throw new ConfigNotValid(self::signature($method) . " has no route.");
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
            throw new ConfigNotValid(
                self::signature($method) . " must be a public, non-static, non-abstract method."
            );
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
        $message = self::signature($method) . " must declare a return type of " . Response::class
            . " or " . IntoResponse::class . ".";

        if (!$type || $type->allowsNull()) {
            throw new ConfigNotValid($message);
        }

        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [ $type ] as $named_type) {
            if (!$named_type instanceof ReflectionNamedType || $named_type->isBuiltin()) {
                throw new ConfigNotValid($message);
            }

            $name = $named_type->getName();

            if ($name === 'self' || $name === 'static') {
                $name = $method->getDeclaringClass()->name;
            }

            if (!is_a($name, Response::class, true) && !is_a($name, IntoResponse::class, true)) {
                throw new ConfigNotValid($message);
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
            throw self::parameter_error($method, $parameter, "variadic parameters are not supported.");
        }

        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            if ($type->getName() === Request::class) {
                return new CurrentRequest();
            }

            if ($type->getName() === Route::class) {
                return new MatchedRoute();
            }
        }

        return self::build_path_parameter($method, $parameter, $patterns);
    }

    /**
     * @param list<Pattern> $patterns
     *
     * @throws ConfigNotValid
     */
    private static function build_path_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        array $patterns
    ): Path {
        [ $type, $backing ] = self::resolve_path_type($method, $parameter);

        $name = $parameter->getName();
        $nullable = $parameter->allowsNull();
        $has_default = $parameter->isDefaultValueAvailable();

        self::assert_in_patterns($method, $parameter, $patterns, $nullable || $has_default);

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
            throw self::parameter_error($method, $parameter, "the type is missing, $expected");
        }

        if (!$type instanceof ReflectionNamedType) {
            throw self::parameter_error($method, $parameter, "the type `$type` is not supported, $expected");
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
            default => throw self::parameter_error(
                $method,
                $parameter,
                "the type `$name` is not supported, $expected"
            ),
        };
    }

    /**
     * A path parameter must be a placeholder of the patterns. It must be one of at least one
     * pattern, otherwise it would never be bound, and one of every pattern unless the parameter has
     * a fallback.
     *
     * @param list<Pattern> $patterns
     * @param bool $has_fallback
     *     Whether the parameter is nullable or has a default value.
     *
     * @throws ConfigNotValid
     */
    private static function assert_in_patterns(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        array $patterns,
        bool $has_fallback
    ): void {
        $name = $parameter->getName();
        $missing = [];

        foreach ($patterns as $pattern) {
            if (!in_array($name, $pattern->params, true)) {
                $missing[] = $pattern;
            }
        }

        if (count($missing) === count($patterns)) {
            throw self::parameter_error(
                $method,
                $parameter,
                sprintf("no `:%s` in %s.", $name, self::format_patterns($patterns))
            );
        }

        if ($missing && !$has_fallback) {
            throw self::parameter_error(
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

    private static function parameter_error(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        string $reason
    ): ConfigNotValid {
        return new ConfigNotValid(
            sprintf("%s parameter `$%s`: %s", self::signature($method), $parameter->getName(), $reason)
        );
    }

    private static function signature(ReflectionMethod $method): string
    {
        return sprintf("`%s::%s()`", $method->class, $method->name);
    }
}
