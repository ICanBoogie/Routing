<?php

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use LogicException;

/**
 * Exception thrown in an attempt to handle a route without action.
 */
class ActionNotDefined extends LogicException implements Exception
{
}
