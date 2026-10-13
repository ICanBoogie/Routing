<?php

namespace ICanBoogie\Routing;

use InvalidArgumentException;
use Throwable;

use function sprintf;

/**
 * Exception thrown in an attempt to format a pattern without a value for one of its parameters.
 */
class PatternValueMissing extends InvalidArgumentException implements Exception
{
    public function __construct(
        public readonly Pattern $pattern,
        public readonly string|int $name,
        ?string $message = null,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?? sprintf("Missing value for parameter '%s' to format pattern: %s.", $name, $pattern),
            previous: $previous
        );
    }
}
