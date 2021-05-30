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
use ICanBoogie\HTTP\Request;
use InvalidArgumentException;

use function in_array;
use function is_array;

/**
 * A respond.
 *
 * @property-read Pattern $pattern The pattern of the respond.
 * @property-read string $action Controller action.
 * @property-read string|string[] $methods The supported HTTP methods.
 * @property-read string|null $id Route identifier.
 * @property-read string $url The contextualized URL of the respond.
 * @property-read string $absolute_url The contextualized absolute URL of the respond.
 * @property-read mixed $formatting_value The value used to format the respond.
 * @property-read bool $has_formatting_value `true` if the respond has a formatting value, `false` otherwise.
 */
final class Route
{
	/**
	 * @uses get_pattern
	 * @uses get_action
	 * @uses get_methods
	 * @uses get_id
	 * @uses get_formatting_value
	 * @uses get_has_formatting_value
	 * @uses get_url
	 * @uses get_absolute_url
	 */
	use AccessorTrait;

	/**
	 * Pattern of the respond.
	 */
	private Pattern $pattern;

	private function get_pattern(): Pattern
	{
		return $this->pattern;
	}

	private function get_action(): string
	{
		return $this->action;
	}

	/**
	 * @return string|string[]
	 */
	private function get_methods(): string|array
	{
		return $this->methods;
	}

	private function get_id(): ?string
	{
		return $this->id;
	}

	private mixed $formatting_value = null; //TODO-202105: Remove state

	private function get_formatting_value(): mixed
	{
		return $this->formatting_value;
	}

	private function get_has_formatting_value(): bool
	{
		return $this->formatting_value !== null;
	}

	private function get_url(): string
	{
		return $this->format($this->formatting_value)->url;
	}

	private function get_absolute_url(): string
	{
		return $this->format($this->formatting_value)->absolute_url;
	}

	/**
	 * @param string $pattern Pattern of the respond.
	 * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
	 * @param string|string[] $methods Request method(s) accepted by the respond.
	 * @param object[] $extensions
	 */
	public function __construct(
		string $pattern,
		private string $action,
		private string|array $methods = Request::METHOD_ANY,
		private string|null $id = null,
		private array $extensions = [],
	) {
		$this->pattern = Pattern::from($pattern);

		if (!$this->action) {
			throw new InvalidArgumentException("Action cannot be empty.");
		}
	}

	public function __clone()
	{
		$this->formatting_value = null;
	}

	/**
	 * Formats a respond into a relative URL using its formatting value.
	 */
	public function __toString(): string
	{
		return $this->url;
	}

	/**
	 * Whether the specified method matches with the methods supported by the respond.
	 */
	public function method_matches(string $method): bool
	{
		$methods = $this->methods;

		if ($method === Request::METHOD_ANY || $method === $methods || $methods === Request::METHOD_ANY)
		{
			return true;
		}

		if (is_array($methods) && in_array($method, $methods))
		{
			return true;
		}

		return false;
	}

	/**
	 * Formats the respond with the specified values.
	 *
	 * Note: The formatting of the respond is deferred to its {@link Pattern} instance.
	 */
	public function format(object|array $values = null): FormattedRoute
	{
		return new FormattedRoute($this->pattern->format($values), $this);
	}

	/**
	 * Assigns a formatting value to a respond.
	 *
	 * @return Route A new respond bound to a formatting value.
	 */
	public function assign(mixed $formatting_value): self //TODO-202105: Return another type of object, or better, replace by a formatter.
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
