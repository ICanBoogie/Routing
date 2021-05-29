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
use InvalidArgumentException;

use function array_combine;
use function array_intersect_key;
use function get_called_class;
use function implode;

/**
 * A route.
 *
 * @property-read Pattern $pattern The pattern of the route.
 * @property-read callable|string $controller The class name of the controller.
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
	/**
	 * @uses get_id
	 * @uses get_location
	 * @uses get_pattern
	 * @uses get_controller
	 * @uses get_action
	 * @uses get_via
	 * @uses get_formatting_value
	 * @uses get_has_formatting_value
	 * @uses get_url
	 * @uses get_absolute_url
	 */
	use AccessorTrait;

	private const INVALID_CONSTRUCT_PROPERTIES = [ 'formatting_value', 'url', 'absolute_url' ];

	/**
	 * Creates a new {@link Route} instance from a route definition.
	 *
	 * @param array<string, mixed> $definition
	 *
	 * @return static
	 */
	static public function from(array $definition): self
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

	private function get_pattern(): Pattern
	{
		return $this->pattern;
	}

	/**
	 * Controller's class name or function.
	 *
	 * @var string|callable
	 */
	private $controller;

	/**
	 * @return string|callable
	 */
	private function get_controller()
	{
		return $this->controller;
	}

	/**
	 * Controller action.
	 *
	 * @var string|null
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
	 */
	protected function get_url(): string
	{
		return $this->format($this->formatting_value)->url;
	}

	/**
	 * Returns absolute URL.
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
	 */
	public function __toString(): string
	{
		return $this->url;
	}

	/**
	 * Asserts that properties are valid.
	 *
	 * @throws InvalidArgumentException if a property is not valid.
	 */
	protected function assert_properties_are_valid(array $properties, array $invalid): void
	{
		$invalid = array_combine($invalid, $invalid);
		$invalid = array_intersect_key($properties, $invalid);

		if (!$invalid)
		{
			return;
		}

		throw new InvalidArgumentException("Invalid construct property: " . implode(', ', $invalid));
	}

	/**
	 * Formats the route with the specified values.
	 *
	 * Note: The formatting of the route is deferred to its {@link Pattern} instance.
	 *
	 * @param object|array|null $values
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
