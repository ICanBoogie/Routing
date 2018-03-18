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
 * Representation of a formatted route.
 *
 * @property-read string $url Relative URL.
 * @property-read string $absolute_url Absolute URL, absolutized with {@link absolutize_url()}.
 * @property-read Route $route The route that was used to format the URL.
 */
class FormattedRoute
{
	use AccessorTrait;

	/**
	 * @var string
	 * @uses get_url
	 * @uses get_absolute_url
	 */
	private $url;

	private function get_url(): string
	{
		return contextualize((string) $this);
	}

	private function get_absolute_url(): string
	{
		return absolutize_url($this->get_url());
	}

	/**
	 * @var Route
	 * @uses get_route
	 */
	private $route;

	private function get_route(): Route
	{
		return $this->route;
	}

	/**
	 * @param string $url A relative URL created by {@link Route::format()}.
	 * @param Route $route
	 */
	public function __construct(string $url, Route $route)
	{
		$this->url = $url;
		$this->route = $route;
	}

	public function __toString(): string
	{
		return $this->url;
	}
}
