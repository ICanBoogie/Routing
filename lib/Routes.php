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

use ICanBoogie\HTTP\Request;
use ICanBoogie\Prototype\MethodNotDefined;

/**
 * A route collection.
 */
class Routes implements \IteratorAggregate, \ArrayAccess
{
	const DEFAULT_ROUTE_CLASS = 'ICanBoogie\Routing\Route';

	/**
	 * Route definitions.
	 *
	 * @var array
	 */
	protected $routes = [];

	/**
	 * Route instances.
	 *
	 * @var Route[]
	 */
	protected $instances = [];

	public function __construct(array $routes=[])
	{
		foreach ($routes as $route_id => $route)
		{
			$this[$route_id] = $route;
		}
	}

	public function __call($method, array $arguments)
	{
		$method = strtoupper($method);

		if ($method === Request::METHOD_ANY || in_array($method, Request::$methods))
		{
			list($pattern, $controller) = $arguments;

			$definition = [

				'pattern' => $pattern,
				'via' => $method,
				'controller' => $controller

			];

			$id = $method . ' ' . $pattern;
			$this[$id] = $definition;

			$this->revoke_cache();

			return $this;
		}

		throw new MethodNotDefined([ $method, $this ]);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->routes);
	}

	public function offsetExists($offset)
	{
		return isset($this->routes[$offset]);
	}

	public function offsetGet($id)
	{
		if (isset($this->instances[$id]))
		{
			return $this->instances[$id];
		}

		if (!$this->offsetExists($id))
		{
			throw new RouteNotDefined($id);
		}

		$properties = $this->routes[$id];

		$class = static::DEFAULT_ROUTE_CLASS;

		if (isset($properties['class']))
		{
			$class = $properties['class'];
		}

		return $this->instances[$id] = new $class($properties['pattern'], $properties);
	}

	/**
	 * Adds or replaces a route.
	 *
	 * @param mixed $offset The identifier of the route.
	 * @param array $route The route definition.
	 *
	 * @throws \LogicException if the route definition is invalid.
	 */
	public function offsetSet($id, $route)
	{
		if (empty($route['pattern']))
		{
			throw new PatternNotDefined(\ICanBoogie\format("Route %id has no pattern. !route", [

				'id' => $id,
				'route' => $route

			]));
		}

		if (empty($route['controller']) && empty($route['location']))
		{
			throw new ControllerNotDefined(\ICanBoogie\format("Route %id has no controller. !route", [

				'id' => $id,
				'route' => $route

			]));
		}

		$this->routes[$id] = $route + [

			'id' => $id,
			'via' => Request::METHOD_ANY

		];

		$this->revoke_cache();
	}

	/**
	 * Removes a route.
	 *
	 * @param string $offset The identifier of the route.
	 */
	public function offsetUnset($offset)
	{
		unset($this->routes[$offset]);

		$this->revoke_cache();
	}

	/**
	 * Search for a route matching the specified pathname and method.
	 *
	 * @param string $uri The URI to match. If the URI includes a query string it is removed
	 * before searching for a matching route.
	 * @param array|null $captured The parameters captured from the URI. If the URI included a
	 * query string, its parsed params are stored under the `__query__` key.
	 * @param string $method One of HTTP\Request::METHOD_* methods.
	 * @param string $namespace Namespace restriction.
	 *
	 * @return Route
	 */
	public function find($uri, &$captured=null, $method=Request::METHOD_ANY, $namespace=null)
	{
		$captured = [];

		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		$parsed = parse_url($uri) + [ 'query' => null ];
		$path = $parsed['path'];

		#
		# Determine if a route matches prerequisites.
		#
		$matchable = function($pattern, $via) use($method, $namespace) {

			# namespace

			if ($namespace && strpos($pattern, $namespace) !== 0)
			{
				return false;
			}

			# via

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
		$map_static = function($routes) use($path, &$matchable) {

			foreach ($routes as $id => $route)
			{
				$pattern = $route['pattern'];
				$via = $route['via'];

				if (!$matchable($pattern, $via) || $pattern != $path)
				{
					continue;
				}

				return $id;
			}
		};

		#
		# Search for a matching dynamic route.
		#
		$map_dynamic = function($routes) use($path, &$matchable, &$captured) {

			foreach ($routes as $id => $route)
			{
				$pattern = $route['pattern'];
				$via = $route['via'];

				if (!$matchable($pattern, $via) || !Pattern::from($pattern)->match($path, $captured))
				{
					continue;
				}

				return $id;
			}
		};

		list($static, $dynamic) = $this->sort_routes();

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
			return;
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
	 * Revoke the cache used by the {@link sort_routes} method.
	 */
	private function revoke_cache()
	{
		$this->static = null;
		$this->dynamic = null;
	}

	/**
	 * Sort routes according to their type and computed weight.
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
	private function sort_routes()
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
			$pattern = $definition['pattern'];
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

		\ICanBoogie\stable_sort($dynamic, function($v, $k) use($weights) {

			return -$weights[$k];

		});

		$this->static = $static;
		$this->dynamic = $dynamic;

		return [ $static, $dynamic ];
	}
}
