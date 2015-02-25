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

use ICanBoogie\Object;

/**
 * A route.
 *
 * @property-read Routes $routes The route collection this route belongs to.
 * @property-read Pattern $pattern The pattern of the route.
 * @property-read string $controller The class name of the controller.
 * @property-read string|null $action Controller action.
 * @property-read string $id Route identifier.
 * @property-read string|null $location Redirection destination.
 * @property-read string|array|null $via The supported HTTP methods.
 * @property-read string $url The contextualized URL of the route.
 * @property-read string $absolute_url The contextualized absolute URL of the route.
 */
class Route extends Object
{
	/**
	 * Pattern of the route.
	 *
	 * @var Pattern
	 */
	private $pattern;

	protected function get_pattern()
	{
		return $this->pattern;
	}

	/**
	 * Controller's class name or function.
	 *
	 * @var string
	 */
	private $controller;

	protected function get_controller()
	{
		return $this->controller;
	}

	/**
	 * Controller action.
	 *
	 * @var string
	 */
	private $action;

	protected function get_action()
	{
		return $this->action;
	}

	/**
	 * Identifier of the route.
	 *
	 * @var string
	 */
	private $id;

	protected function get_id()
	{
		return $this->id;
	}

	/**
	 * Redirect location.
	 *
	 * If the property is defined the route is considered an alias.
	 *
	 * @var string
	 */
	private $location;

	protected function get_location()
	{
		return $this->location;
	}

	/**
	 * Request methods accepted by the route.
	 *
	 * @var string
	 */
	private $via;

	protected function get_via()
	{
		return $this->via;
	}

	/**
	 * The route collection this route belongs to.
	 *
	 * @var Routes
	 */
	private $routes;

	protected function get_routes()
	{
		return $this->routes;
	}

	/**
	 * Returns relative URL.
	 *
	 * @return string
	 */
	protected function get_url()
	{
		if (isset($this->url_provider))
		{
			$class = $this->url_provider;
			$provider = new $class();

			return $provider($this);
		}

		return $this->format()->url;
	}

	/**
	 * Returns absolute URL.
	 *
	 * @return string
	 */
	protected function get_absolute_url()
	{
		return $this->format()->absolute_url;
	}

	/**
	 * Initializes the {@link $pattern} property and the properties provided.
	 *
	 * @param Routes $routes
	 * @param string $pattern
	 * @param array $properties
	 */
	public function __construct(Routes $routes, $pattern, array $properties)
	{
		$this->routes = $routes;
		$this->pattern = Pattern::from($pattern);

		unset($properties['pattern']);

		foreach ($properties as $property => $value)
		{
			$this->$property = $value;
		}
	}

	/**
	 * Returns the pattern of the route.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->pattern;
	}

	/**
	 * Formats the route with the specified values.
	 *
	 * Note: The formatting of the route is deferred to its {@link Pattern} instance.
	 *
	 * @param object|array|null $values
	 *
	 * @return FormattedRoute
	 */
	public function format($values = null)
	{
		return new FormattedRoute($this->pattern->format($values), $this);
	}
}
