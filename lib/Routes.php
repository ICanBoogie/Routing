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

			if ($method === Request::METHOD_GET)
			{
				$this[Request::METHOD_HEAD . ' ' . $pattern] = array_merge($definition, [ 'via' => Request::METHOD_HEAD ]);
			}

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
			throw new \LogicException(format("Route %id has no pattern. !route", [

				'id' => $id,
				'route' => $route

			]));
		}

		$this->routes[$id] = $route + [

			'id' => $id,
			'via' => Request::METHOD_ANY

		];
	}

	static public function add($id, $definition)
	{
		$routes = static::get();
		$routes[$id] = $definition;
	}

	/**
	 * Removes a route.
	 *
	 * @param string $offset The identifier of the route.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset)
	{
		unset($this->routes[$offset]);
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

		$pattern = null;
		$parsed = parse_url($uri) + [ 'query' => null ];
		$path = $parsed['path'];

		foreach ($this->routes as $id => $route)
		{
			# namespace

			$pattern = $route['pattern'];

			if ($namespace && strpos($pattern, $namespace) !== 0)
			{
				continue;
			}

			# via

			if ($method != Request::METHOD_ANY)
			{
				$via = $route['via'];

				if (is_array($via))
				{
					if (!in_array($method, $via))
					{
						continue;
					}
				}
				else if ($via !== Request::METHOD_ANY && $via !== $method)
				{
					continue;
				}
			}

			# pattern

			if (!Pattern::from($pattern)->match($path, $captured))
			{
				continue;
			}

			# found it!

			$query = $parsed['query'];

			if ($query)
			{
				parse_str($query, $parsed_query_string);

				$captured['__query__'] = $parsed_query_string;
			}

			return $this[$id];
		}
	}
}