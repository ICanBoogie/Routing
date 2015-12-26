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

/**
 * Exception thrown in attempt to format a pattern requiring values without providing any.
 *
 * @property-read Pattern $pattern
 */
class PatternRequiresValues extends \InvalidArgumentException implements Exception
{
	use AccessorTrait;

	/**
	 * @var Pattern
	 */
	private $pattern;

	protected function get_pattern()
	{
		return $this->pattern;
	}

	/**
	 * @param Pattern $pattern
	 * @param string $message
	 * @param int $code
	 * @param \Exception|null $previous
	 */
	public function __construct(Pattern $pattern, $message = "The pattern requires values to be formatted.", $code = 500, \Exception $previous = null)
	{
		$this->pattern = $pattern;

		parent::__construct($message, $code, $previous);
	}
}
