<?php

namespace ICanBoogie\Routing;

use Throwable;

/**
 * Routing exceptions implement this interface so that they can be easily recognized.
 *
 * <pre>
 * try
 * {
 *     // …
 * }
 * catch (\ICanBoogie\Routing\Exception $e)
 * {
 *     // a routing exception
 * }
 * catch (\Throwable $e
 * {
 *     // another type of exception
 * }
 * </pre>
 */
interface Exception extends Throwable
{
}
