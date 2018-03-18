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

	private const INVALID_CONSTRUCT_PROPERTIES = [ 'formatting_value', 'url', 'absolute_url' ];

	/**
	 * Creates a new {@link Route} instance from a route definition.
	 *
	 * @param array $definition
	 *
	 * @return static
	 */
	static public function from(array $definition): self
	{
		$class = \get_called_class();

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
	 * @uses get_pattern
	 */
	private $pattern;

	private function get_pattern(): Pattern
	{
		return $this->pattern;
	}

	/**
	 * Controller's class name or function.
	 *
	 * @var mixed @todo Should be string|null
	 * @uses get_controller
	 */
	private $controller;

	private function get_controller()
	{
		return $this->controller;
	}

	/**
	 * Controller action.
	 *
	 * @var string|null
	 * @uses get_action
	 */
	private $action;

	private function get_action(): ?string
	{
		return $this->action;
	}

	/**
	 * Identifier of the route.
	 *
	 * @var string|null
	 * @uses get_id
	 */
	private $id;

	private function get_id(): ?string
	{
		return $this->id;
	}

	/**
	 * Redirect location.
	 *
	 * If the property is defined the route is considered an alias.
	 *
	 * @var string|null
	 * @uses get_location
	 */
	private $location;

	private function get_location(): ?string
	{
		return $this->location;
	}

	/**
	 * Request methods accepted by the route.
	 *
	 * @var string|array|null
	 * @uses get_via
	 */
	private $via;

	private function get_via()
	{
		return $this->via;
	}

	/**
	 * Formatting value.
	 *
	 * @var mixed
	 * @uses get_formatting_value
	 * @uses get_has_formatting_value
	 */
	private $formatting_value;

	private function get_formatting_value()
	{
		return $this->formatting_value;
	}

	private function get_has_formatting_value(): bool
	{
		return $this->formatting_value !== null;
	}

	/**
	 * Returns relative URL.
	 *
	 * @return string
	 */
	protected function get_url(): string
	{
		return $this->format($this->formatting_value)->url;
	}

	/**
	 * Returns absolute URL.
	 *
	 * @return string
	 */
	protected function get_absolute_url(): string
	{
		return $this->format($this->formatting_value)->absolute_url;
	}

	public function __construct(string $pattern, array $properties)
	{
		$this->pattern = Pattern::from($pattern);

		unset($properties['pattern']);

		$this->assert_properties_are_valid($properties, self::INVALID_CONSTRUCT_PROPERTIES);

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
	protected function assert_properties_are_valid(array $properties, array $invalid): void
	{
		$invalid = \array_combine($invalid, $invalid);
		$invalid = \array_intersect_key($properties, $invalid);

		if (!$invalid)
		{
			return;
		}

		throw new \InvalidArgumentException("Invalid construct property: " . \implode(', ', $invalid));
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
	public function format($values = null): FormattedRoute
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
	public function assign($formatting_value): self
	{
		$clone = clone $this;

		#
		# We could write directly to `formatting_value`, but since it is marked _read-only_
		# we resort to shenanigans to keep the IDE happy :)
		#

		$ref = &$clone->formatting_value;
		$ref = $formatting_value;

		return $clone;
	}
}
