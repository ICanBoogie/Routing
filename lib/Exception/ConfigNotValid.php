<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use LogicException;

/**
 * Thrown when the configuration is not valid, for instance when a parameter of an action cannot be
 * bound, or when a method cannot be an action.
 *
 * These are wiring mistakes, they are detected when the configuration is built, not when requests
 * are handled.
 */
class ConfigNotValid extends LogicException implements Exception
{
}
