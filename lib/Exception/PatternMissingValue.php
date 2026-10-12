<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\PatternRequiresValues;
use Throwable;

/**
 * Exception thrown in an attempt to format a pattern when a value is missing for one of its placeholders.
 */
final class PatternMissingValue extends PatternRequiresValues
{
    public function __construct(
        Pattern $pattern,
        public readonly string|int $key,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $pattern,
            "Missing value for placeholder '$key' to format pattern '$pattern'.",
            $previous
        );
    }
}
