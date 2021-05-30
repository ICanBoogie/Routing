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
use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown when a respond pattern is blank.
 */
class InvalidPattern extends InvalidArgumentException implements Exception
{
	public function __construct($message = "Invalid pattern.", Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
	}
}
