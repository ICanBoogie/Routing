<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\HTTP\NotFound;
use ICanBoogie\Routing\Exception;
use Throwable;

/**
 * Thrown when a path value cannot be cast to the type of the action's parameter, for instance
 * `/articles/abc` for an `int $id`. The resource designated by the URL doesn't exist.
 */
class PathParameterNotValid extends NotFound implements Exception
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        ?Throwable $previous = null,
    ) {
        parent::__construct("The path parameter '$name' is not a valid $type.", previous: $previous);
    }
}
