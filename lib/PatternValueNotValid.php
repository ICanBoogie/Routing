<?php

namespace ICanBoogie\Routing;

use InvalidArgumentException;
use Throwable;

use function sprintf;

/**
 * Exception thrown in an attempt to format a pattern with a value that the pattern would not match,
 * because it doesn't satisfy the constraint of its parameter.
 */
class PatternValueNotValid extends InvalidArgumentException implements Exception
{
    public function __construct(
        public readonly Pattern $pattern,
        public readonly string|int $name,
        public readonly string $value,
        ?string $message = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?? sprintf(
                "The value '%s' of parameter '%s' does not satisfy its constraint in pattern: %s.",
                $value,
                $name,
                $pattern
            ),
            previous: $previous
        );
    }
}
