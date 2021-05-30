<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Accessor\AccessorTrait;
use ICanBoogie\HTTP\Request;

/**
 * @property-read Pattern $pattern
 * @property-read string $location
 * @property-read string|string[] $methods
 */
final class Redirection
{
	/**
	 * @uses get_pattern
	 * @uses get_location
	 * @uses get_via
	 */
	use AccessorTrait;

	private Pattern $pattern;

	private function get_pattern(): Pattern
	{
		return $this->pattern;
	}

	private function get_location(): string
	{
		return $this->location;
	}

	private function get_via(): string|array
	{
		return $this->via;
	}

	/**
	 * @param string $pattern Pattern of the respond.
	 * @param string $location A target location.
	 * @param string|string[] $via Request method(s) accepted by the respond.
	 */
	public function __construct(
		string $pattern,
		private string $location,
		private string|array $via = Request::METHOD_ANY,
	)
	{
		$this->pattern = Pattern::from($pattern);
	}
}
