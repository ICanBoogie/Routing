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

use ArrayAccess;
use ArrayIterator;
use Countable;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Prototype\MethodNotDefined;
use IteratorAggregate;

use function count;
use function ICanBoogie\stable_sort;
use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function strpos;
use function strtoupper;
use function substr_count;

/**
 * A route collection.
 *
 * @method RouteCollection any() any(string $pattern, $controller, array $options=[]) Add a route for any HTTP method.
 * @method RouteCollection connect() connect(string $pattern, $controller, array $options=[]) Add a route for the HTTP method CONNECT.
 * @method RouteCollection delete() delete(string $pattern, $controller, array $options=[]) Add a route for the HTTP method DELETE.
 * @method RouteCollection get() get(string $pattern, $controller, array $options=[]) Add a route for the HTTP method GET.
 * @method RouteCollection head() head(string $pattern, $controller, array $options=[]) Add a route for the HTTP method HEAD.
 * @method RouteCollection options() options(string $pattern, $controller, array $options=[]) Add a route for the HTTP method OPTIONS.
 * @method RouteCollection post() post(string $pattern, $controller, array $options=[]) Add a route for the HTTP method POST.
 * @method RouteCollection put() put(string $pattern, $controller, array $options=[]) Add a route for the HTTP method PUT.
 * @method RouteCollection patch() patch(string $pattern, $controller, array $options=[]) Add a route for the HTTP method PATCH
 * @method RouteCollection trace() trace(string $pattern, $controller, array $options=[]) Add a route for the HTTP method TRACE.
 *
 * @template-implements IteratorAggregate<string, Route>
 */
class RouteCollection implements IteratorAggregate, ArrayAccess, Countable
{
	/**
	 * Specify that the route definitions can be trusted.
	 */
	public const TRUSTED_DEFINITIONS = true;

	/**
	 * Class name of the {@link Route} instances.
	 */
	public const DEFAULT_ROUTE_CLASS = Route::class;

	/**
	 * Route definitions.
	 *
	 * @var array<string, array>
	 */
	private $routes = [];

	/**
	 * Route instances.
	 *
	 * @var array<string, Route>
	 */
	private $instances = [];

	/**
	 * @param array<string, array> $definitions
	 * @param bool $trusted_definitions {@link TRUSTED_DEFINITIONS} if the definition can be
	 * trusted. This will speed up the construct process but the definitions will not be checked,
	 * nor will they be normalized.
	 */
	public function __construct(array $definitions = [], bool $trusted_definitions = false)
	{
		foreach ($definitions as $id => $definition)
		{
			if (is_string($id) && empty($definition[RouteDefinition::ID]))
			{
				$definition[RouteDefinition::ID] = $id;
			}

			$this->add($definition, $trusted_definitions);
		}
	}

	/**
	 * Adds a route definition using an HTTP method.
	 */
	public function __call(string $method, array $arguments): self
	{
		$method = strtoupper($method);

		if ($method !== Request::METHOD_ANY && !in_array($method, Request::METHODS))
		{
			throw new MethodNotDefined($method, $this);
		}

		[ $pattern, $controller, $options ] = $arguments + [ 2 => [] ];

		$this->revoke_cache();
		$this->add([

			RouteDefinition::CONTROLLER => $controller,
			RouteDefinition::PATTERN => $pattern

		] + $options + [ RouteDefinition::VIA => $method ]);

		return $this;
	}

	/**
	 * Adds a route definition.
	 *
	 * **Note:** The method does *not* revoke cache.
	 *
	 * @param bool $trusted_definition {@link TRUSTED_DEFINITIONS} if the method should be trusting the
	 * definition, in which case the method doesn't assert if the definition is valid, nor does
	 * it normalizes it.
	 */
	protected function add(array $definition, bool $trusted_definition = false): RouteCollection
	{
		if (!$trusted_definition)
		{
			RouteDefinition::assert_is_valid($definition);
			RouteDefinition::normalize($definition);
			RouteDefinition::ensure_has_id($definition);
		}

		$id = $definition[RouteDefinition::ID];
		$this->routes[$id] = $definition;

		return $this;
	}

	/**
	 * Adds resource routes.
	 *
	 * **Note:** The route definitions for the resource are created by
	 * {@link RouteMaker::resource}. Both methods accept the same arguments.
	 *
	 * @see \ICanBoogie\Routing\RoutesMaker::resource
	 */
	public function resource(string $name, string $controller, array $options = []): void
	{
		$definitions = RouteMaker::resource($name, $controller, $options);
		$this->revoke_cache();

		foreach ($definitions as $id => $definition)
		{
			$this->add([ RouteDefinition::ID => $id ] + $definition);
		}
	}

	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->routes);
	}

	/**
	 * @param string $offset Route identifier.
	 */
	public function offsetExists($offset): bool
	{
		return isset($this->routes[$offset]);
	}

	/**
	 * Returns a {@link Route} instance.
	 *
	 * @param string $offset Route identifier.
	 *
	 * @throws RouteNotDefined
	 */
	public function offsetGet($offset): Route
	{
		if (isset($this->instances[$offset]))
		{
			return $this->instances[$offset];
		}

		if (!$this->offsetExists($offset))
		{
			throw new RouteNotDefined($offset);
		}

		return $this->instances[$offset] = Route::from($this->routes[$offset]);
	}

	/**
	 * Defines a route.
	 *
	 * @param string $offset The identifier of the route.
	 * @param array $value The route definition.
	 */
	public function offsetSet($offset, $value): void
	{
		$this->revoke_cache();
		$this->add([ RouteDefinition::ID => $offset ] + $value);
	}

	/**
	 * Removes a route.
	 *
	 * @param string $offset The identifier of the route.
	 */
	public function offsetUnset($offset): void
	{
		unset($this->routes[$offset]);

		$this->revoke_cache();
	}

	/**
	 * Returns the number of routes in the collection.
	 *
	 * @inheritdoc
	 */
	public function count(): int
	{
		return count($this->routes);
	}

	/**
	 * Search for a route matching the specified pathname and method.
	 *
	 * @param string $uri The URI to match. If the URI includes a query string it is removed
	 * before searching for a matching route.
	 * @param array|null $captured The parameters captured from the URI. If the URI included a
	 * query string, its parsed params are stored under the `__query__` key.
	 * @param string $method One of HTTP\Request::METHOD_* methods.
	 *
	 * @return Route|false|null
	 */
	public function find(string $uri, array &$captured = null, string $method = Request::METHOD_ANY)
	{
		$captured = [];

		$parsed = (array) parse_url($uri) + [ 'path' => null, 'query' => null ];
		$path = $parsed['path'];

		if (!$path)
		{
			return false;
		}

		#
		# Determine if a route matches prerequisites.
		#
		$matchable = function($via) use($method) {

			if ($method != Request::METHOD_ANY)
			{
				if (is_array($via))
				{
					if (!in_array($method, $via))
					{
						return false;
					}
				}
				else if ($via !== Request::METHOD_ANY && $via !== $method)
				{
					return false;
				}
			}

			return true;
		};

		#
		# Search for a matching static route.
		#
		$map_static = function($definitions) use($path, &$matchable) {

			foreach ($definitions as $id => $definition)
			{
				$pattern = $definition[RouteDefinition::PATTERN];
				$via = $definition[RouteDefinition::VIA];

				if (!$matchable($via) || $pattern != $path)
				{
					continue;
				}

				return $id;
			}

			return null;
		};

		#
		# Search for a matching dynamic route.
		#
		$map_dynamic = function($definitions) use($path, &$matchable, &$captured) {

			foreach ($definitions as $id => $definition)
			{
				$pattern = $definition[RouteDefinition::PATTERN];
				$via = $definition[RouteDefinition::VIA];

				if (!$matchable($via) || !Pattern::from($pattern)->match($path, $captured))
				{
					continue;
				}

				return $id;
			}

			return null;
		};

		[ $static, $dynamic ] = $this->sort_routes();

		$id = null;

		if ($static)
		{
			$id = $map_static($static);
		}

		if (!$id && $dynamic)
		{
			$id = $map_dynamic($dynamic);
		}

		if (!$id)
		{
			return null;
		}

		$query = $parsed['query'];

		if ($query)
		{
			parse_str($query, $parsed_query_string);

			$captured['__query__'] = $parsed_query_string;
		}

		return $this[$id];
	}

	private $static;
	private $dynamic;

	/**
	 * Revokes the cache used by the {@link sort_routes} method.
	 */
	private function revoke_cache(): void
	{
		$this->static = null;
		$this->dynamic = null;
	}

	/**
	 * Sorts routes according to their type and computed weight.
	 *
	 * Routes and grouped in two groups: static routes and dynamic routes. The difference between
	 * static and dynamic routes is that dynamic routes capture parameters from the path and thus
	 * require a regex to compute the match, whereas static routes only require is simple string
	 * comparison.
	 *
	 * Dynamic routes are ordered according to their weight, which is computed from the number
	 * of static parts before the first capture. The more static parts, the lighter the route is.
	 *
	 * @return array An array with the static routes and dynamic routes.
	 */
	private function sort_routes(): array
	{
		if ($this->static !== null)
		{
			return [ $this->static, $this->dynamic ];
		}

		$static = [];
		$dynamic = [];
		$weights = [];

		foreach ($this->routes as $id => $definition)
		{
			$pattern = $definition[RouteDefinition::PATTERN];
			$first_capture_position = strpos($pattern, ':') ?: strpos($pattern, '<');

			if ($first_capture_position === false)
			{
				$static[$id] = $definition;
			}
			else
			{
				$dynamic[$id] = $definition;
				$weights[$id] = substr_count($pattern, '/', 0, $first_capture_position);
			}
		}

		stable_sort($dynamic, function($v, $k) use($weights) {

			return -$weights[$k];

		});

		$this->static = $static;
		$this->dynamic = $dynamic;

		return [ $static, $dynamic ];
	}

	/**
	 * Returns a new collection with filtered routes.
	 */
	public function filter(callable $filter): RouteCollection
	{
		$definitions = [];

		foreach ($this as $id => $definition)
		{
			if (!$filter($definition, $id))
			{
				continue;
			}

			$definitions[$id] = $definition;
		}

		return new static($definitions);
	}
}
