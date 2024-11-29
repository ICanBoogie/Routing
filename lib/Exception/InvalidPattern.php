<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown when a route pattern is blank.
 */
class InvalidPattern extends InvalidArgumentException implements Exception
{
    public function __construct(
        string $message = "Invalid pattern",
        ?Throwable $previous = null
    ) {
        parent::__construct($message, previous: $previous);
    }
}
