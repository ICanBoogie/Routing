<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use LogicException;
use ReflectionMethod;
use ReflectionParameter;

use function sprintf;

/**
 * Thrown when the configuration is not valid, for instance when a parameter of an action cannot be
 * bound, or when a method cannot be an action.
 *
 * These are wiring mistakes, they are detected when the configuration is built, not when requests
 * are handled.
 */
class ConfigNotValid extends LogicException implements Exception
{
    /**
     * The mistake is about a method, e.g. "`SkillController::show()` has no route."
     */
    public static function for_method(ReflectionMethod $method, string $reason): self
    {
        return new self(sprintf("`%s::%s()` %s", $method->class, $method->name, $reason));
    }

    /**
     * The mistake is about a parameter of a method, e.g. "`SkillController::show()` parameter
     * `$slug`: no `:slug` in `/skills/:id`."
     */
    public static function for_parameter(
        ReflectionMethod $method,
        ReflectionParameter $parameter,
        string $reason
    ): self {
        return self::for_method($method, sprintf("parameter `$%s`: %s", $parameter->getName(), $reason));
    }
}
