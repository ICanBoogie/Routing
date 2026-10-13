<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\HTTP\ClientError;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Routing\Exception;
use Throwable;

/**
 * Thrown when a value provided by the client, in the query string or in the body, is missing or
 * cannot be cast to the type of the action's parameter. The request is not valid.
 *
 * A path value that cannot be cast is a {@see PathParameterNotValid}, a "not found".
 */
class ParameterNotValid extends ClientError implements Exception
{
    /**
     * @param 'query'|'part'|'body' $source
     *     Where the value is read from.
     * @param string $name
     *     Name of the value in the source.
     * @param string $type
     *     Type of the parameter.
     * @param bool $missing
     *     Whether the value is missing, as opposed to not castable.
     */
    public function __construct(
        public readonly string $source,
        public readonly string $name,
        public readonly string $type,
        public readonly bool $missing = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $missing
                ? "The $source parameter '$name' is required."
                : "The $source parameter '$name' is not a valid $type.",
            Status::BAD_REQUEST,
            $previous
        );
    }
}
