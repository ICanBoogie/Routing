<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\Exception;
use UnexpectedValueException;

/**
 * Thrown by a {@see Decoder} when a value cannot be turned into an object.
 *
 * The parameter reports it as a client error: a "not found" for a path value, a "bad request" for
 * the other sources.
 */
class NotDecodable extends UnexpectedValueException implements Exception
{
}
