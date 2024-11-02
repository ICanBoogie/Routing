<?php

namespace ICanBoogie\Routing;

use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown in an attempt to format a pattern requiring values without providing any.
 */
class PatternRequiresValues extends InvalidArgumentException implements Exception
{
    public function __construct(
        public readonly Pattern $pattern,
        string $message = "The pattern requires values to be formatted.",
        ?Throwable $previous = null
    ) {
        parent::__construct($message, previous: $previous);
    }
}
