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
use ICanBoogie\HTTP\RequestMethod;
use InvalidArgumentException;

use function in_array;
use function is_array;

/**
 * A respond.
 *
 * @property-read string $url The contextualized URL of the route.
 * @property-read string $absolute_url The contextualized absolute URL of the route.
 * @property-read mixed $formatting_value The value used to format the route.
 * @property-read bool $has_formatting_value `true` if the route has a formatting value, `false` otherwise.
 */
final class Route
{
	/**
	 * @uses get_formatting_value
	 * @uses get_has_formatting_value
	 * @uses get_url
	 * @uses get_absolute_url
	 */
	use AccessorTrait;

	/**
	 * Pattern of the response.
	 */
	readonly public Pattern $pattern;

	/**
	 * @var array<int|string, mixed>|object|null
	 */
	private array|object|null $formatting_value = null; //TODO-202105: Remove state

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
	 * @param string $pattern Pattern of the route.
	 * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
	 * @param RequestMethod|RequestMethod[] $methods Request method(s) accepted by the respond.
	 * @param object[] $extensions
	 */
	public function __construct(
		string $pattern,
		public readonly string $action,
		public readonly RequestMethod|array $methods = RequestMethod::METHOD_ANY,
		public readonly string|null $id = null,
		public readonly array $extensions = [],
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
	 * Formats a response into a relative URL using its formatting value.
	 */
	public function __toString(): string
	{
		return $this->url;
	}

	/**
	 * Whether the specified method matches with the methods supported by the route.
	 */
	public function method_matches(RequestMethod $method): bool
	{
		$methods = $this->methods;

		// TODO: what's up with `$method === $methods`?
		if ($method === RequestMethod::METHOD_ANY || $method === $methods || $methods === RequestMethod::METHOD_ANY)
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
	 * Formats the route with the specified values.
	 *
	 * Note: The formatting of the route is deferred to its {@link Pattern} instance.
	 *
	 * @param array<string, mixed>|object|null $values
	 *
	 * @return FormattedRoute
	 */
	public function format(object|array $values = null): FormattedRoute
	{
		return new FormattedRoute($this->pattern->format($values), $this);
	}

	/**
	 * Assigns a formatting value to a respond.
	 *
	 * @param array<int|string, mixed>|object $formatting_value
	 *
	 * @return Route A new route bound to a formatting value.
	 */
	public function assign(array|object $formatting_value): self //TODO-202105: Return another type of object, or better, replace by a formatter.
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
