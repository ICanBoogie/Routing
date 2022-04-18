<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown in attempt to format a pattern requiring values without providing any.
 */
class PatternRequiresValues extends InvalidArgumentException implements Exception
{
    public function __construct(
        public readonly Pattern $pattern,
        string $message = "The pattern requires values to be formatted.",
        Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
