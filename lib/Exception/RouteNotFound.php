<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use LogicException;
use Throwable;

class RouteNotFound extends LogicException implements Exception
{
    public const DEFAULT_MESSAGE = "Unable to find route with the specified predicate.";

    public function __construct(
        string $message = self::DEFAULT_MESSAGE,
        public readonly mixed $predicate = null,
        Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
