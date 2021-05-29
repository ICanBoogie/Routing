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

use ICanBoogie\Accessor\AccessorTrait;
use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown in attempt to format a pattern requiring values without providing any.
 *
 * @property-read Pattern $pattern
 */
class PatternRequiresValues extends InvalidArgumentException implements Exception
{
	/**
	 * @uses get_pattern
	 */
	use AccessorTrait;

	/**
	 * @var Pattern
	 */
	private $pattern;

	private function get_pattern(): Pattern
	{
		return $this->pattern;
	}

	public function __construct(Pattern $pattern, string $message = "The pattern requires values to be formatted.", int $code = 500, Throwable $previous = null)
	{
		$this->pattern = $pattern;

		parent::__construct($message, $code, $previous);
	}
}
