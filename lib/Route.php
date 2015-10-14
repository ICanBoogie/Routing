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
 * A route.
 *
 * @property-read Pattern $pattern The pattern of the route.
 * @property-read string $controller The class name of the controller.
 * @property-read string|null $action Controller action.
 * @property-read string $id Route identifier.
 * @property-read string|null $location Redirection destination.
 * @property-read string|array|null $via The supported HTTP methods.
 * @property-read string $url The contextualized URL of the route.
 * @property-read string $absolute_url The contextualized absolute URL of the route.
 * @property-read mixed $formatting_value The value used to format the route.
 * @property-read bool $has_formatting_value `true` if the route has a formatting value, `false` otherwise.
 */
class Route
{
	use AccessorTrait;

	static protected $invalid_construct_properties = [ 'formatting_value', 'url', 'absolute_url' ];

	/**
	 * Creates a new {@link Route} instance from a route definition.
	 *
	 * @param array $definition
	 *
	 * @return static
	 */
	static public function from(array $definition)
	{
		$class = get_called_class();

		if (isset($definition[RouteDefinition::CONSTRUCTOR]))
		{
			$class = $definition[RouteDefinition::CONSTRUCTOR];
		}

		return new $class($definition[RouteDefinition::PATTERN], $definition);
	}

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
	 * Formatting value.
	 *
	 * @var mixed
	 */
	private $formatting_value;

	/**
	 * Returns the formatting value.
	 *
	 * @return mixed
	 */
	protected function get_formatting_value()
	{
		return $this->formatting_value;
	}

	/**
	 * Whether the route has a formatting value.
	 *
	 * @return bool `true` if the route has a formatting value, `false` otherwise.
	 */
	protected function get_has_formatting_value()
	{
		return $this->formatting_value !== null;
	}

	/**
	 * Returns relative URL.
	 *
	 * @return string
	 */
	protected function get_url()
	{
		return $this->format($this->formatting_value)->url;
	}

	/**
	 * Returns absolute URL.
	 *
	 * @return string
	 */
	protected function get_absolute_url()
	{
		return $this->format($this->formatting_value)->absolute_url;
	}

	/**
	 * Initializes the {@link $pattern} property and the properties provided.
	 *
	 * @param string $pattern
	 * @param array $properties
	 */
	public function __construct($pattern, array $properties)
	{
		$this->pattern = Pattern::from($pattern);

		unset($properties['pattern']);

		$this->assert_properties_are_valid($properties, self::$invalid_construct_properties);

		foreach ($properties as $property => $value)
		{
			$this->$property = $value;
		}
	}

	public function __clone()
	{
		$this->formatting_value = null;
	}

	/**
	 * Formats a route into a relative URL using its formatting value.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->url;
	}

	/**
	 * Asserts that properties are valid.
	 *
	 * @param array $properties
	 * @param array $invalid
	 *
	 * @throws \InvalidArgumentException if a property is not valid.
	 */
	protected function assert_properties_are_valid(array $properties, array $invalid)
	{
		$invalid = array_combine($invalid, $invalid);
		$invalid = array_intersect_key($properties, $invalid);

		if (!$invalid)
		{
			return;
		}

		throw new \InvalidArgumentException("Invalid construct property: " . implode(', ', $invalid));
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

	/**
	 * Assigns a formatting value to a route.
	 *
	 * @param mixed $formatting_value A formatting value.
	 *
	 * @return Route A new route bound to a formatting value.
	 */
	public function assign($formatting_value)
	{
		$clone = clone $this;
		$clone->formatting_value = $formatting_value;

		return $clone;
	}
}
