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
 * Representation of a formatted respond.
 *
 * @property-read string $url Relative URL.
 * @property-read string $absolute_url Absolute URL, absolutized with {@link absolutize_url()}.
 */
class FormattedRoute
{
	/**
	 * @uses get_url
	 * @uses get_absolute_url
	 */
	use AccessorTrait;

	private function get_url(): string
	{
		return contextualize($this->url);
	}

	private function get_absolute_url(): string
	{
		return absolutize_url($this->get_url());
	}

	/**
	 * @param string $url A relative URL created by {@link Route::format()}.
	 */
	public function __construct(
		private readonly string $url,
		public readonly Route $route
	) {
	}

	public function __toString(): string
	{
		return $this->url;
	}
}
