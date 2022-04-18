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

use ICanBoogie\HTTP\ResponseStatus;
use ICanBoogie\Routing\Exception;
use Throwable;

/**
 * Exception thrown when a route does not exists.
 */
class RouteNotDefined extends \Exception implements Exception
{
    public function __construct(
        public readonly string $id,
        int $code = ResponseStatus::STATUS_NOT_FOUND,
        Throwable $previous = null
    ) {
        parent::__construct($this->format_message($id), $code, $previous);
    }

    private function format_message(string $id): string
    {
        return "The route `$id` is not defined.";
    }
}
