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

use ICanBoogie\GetterTrait;

/**
 * Exception thrown in attempt to format a pattern requiring values without providing any.
 */
class PatternRequiresValues extends \InvalidArgumentException implements Exception
{
	use GetterTrait;

	private $pattern;

	protected function get_pattern()
	{
		return $this->pattern;
	}

	public function __construct(Pattern $pattern, $message=null, $code=500, \Exception $previous=null)
	{
		$this->pattern = $pattern;

		if (!$message)
		{
			$message = "The pattern requires values to be formatted.";
		}

		parent::__construct($message, $code, $previous);
	}
}
