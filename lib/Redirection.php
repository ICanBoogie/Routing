<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;

final class Redirection
{
	public readonly Pattern $pattern;

	/**
	 * @param string $pattern Pattern of the respond.
	 * @param string $location A target location.
	 * @param RequestMethod|RequestMethod[] $methods Request method(s) accepted by the respond.
	 */
	public function __construct(
		string $pattern,
		public readonly string $location,
		public readonly RequestMethod|array $methods = RequestMethod::METHOD_ANY,
	) {
		$this->pattern = Pattern::from($pattern);
	}
}
