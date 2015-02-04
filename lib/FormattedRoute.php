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
	 * The relative URL created by {@link Route::format()}.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * The {@link Route} instance that created the relative URL.
	 *
	 * @var Route
	 */
	protected $route;

	/**
	 * Initialize the {@link $url} and {@link $route} properties.
	 *
	 * @param string $url
	 * @param Route $route
	 */
	public function __construct($url, Route $route)
	{
		$this->url = $url;
		$this->route = $route;
	}

	public function __toString()
	{
		return $this->url;
	}

	protected function get_url()
	{
		return contextualize((string) $this);
	}

	protected function get_route()
	{
		return $this->route;
	}

	protected function get_absolute_url()
	{
		return absolutize_url($this->url);
	}
}
