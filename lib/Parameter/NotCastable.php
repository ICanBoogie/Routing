<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\Routing\Exception;
use UnexpectedValueException;

/**
 * Thrown when a value cannot be cast to the type of a parameter.
 *
 * Parameters catch it to throw an exception that maps to the source of the value: a path value
 * that cannot be cast is a "not found", whereas other sources are a "bad request".
 *
 * @internal
 */
final class NotCastable extends UnexpectedValueException implements Exception
{
}
