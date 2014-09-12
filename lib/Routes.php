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

use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
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

			$definition = array
			(
				'pattern' => $pattern,
				'via' => $method,
				'controller' => $controller
			);

			$id = $method . ' ' . $pattern;
			$this[$id] = $definition;

			if ($method === Request::METHOD_GET)
			{
				$this[Request::METHOD_HEAD . ' ' . $pattern] = array_merge($definition, array('via' => Request::METHOD_HEAD));
			}

			return $this[$id];
		}

		throw new MethodNotDefined(array($method, $this));
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
			throw new \LogicException(format
			(
				"Route %id has no pattern. !route", array
				(
					'id' => $id,
					'route' => $route
				)
			));
		}

		$this->routes[$id] = $route + array
		(
			'id' => $id,
			'via' => Request::METHOD_ANY
		);
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
		$captured = array();

		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		$found = null;
		$pattern = null;

		$qs = null;
		$qs_pos = strpos($uri, '?');

		if ($qs_pos !== false)
		{
			$qs = substr($uri, $qs_pos + 1);
			$uri = substr($uri, 0, $qs_pos);
		}

		foreach ($this->routes as $id => $route)
		{
			$pattern = $route['pattern'];

			if ($namespace && strpos($pattern, $namespace) !== 0)
			{
				continue;
			}

			$pattern = Pattern::from($pattern);

			if (!$pattern->match($uri, $captured))
			{
				continue;
			}

			if ($method == Request::METHOD_ANY)
			{
				$found = true;
				break;
			}

			$route_method = $route['via'];

			if (is_array($route_method))
			{
				if (in_array($method, $route_method))
				{
					$found = true;
					break;
				}
			}
			else
			{
				if ($route_method === Request::METHOD_ANY || $route_method === $method)
				{
					$found = true;
					break;
				}
			}
		}

		if (!$found)
		{
			return;
		}

		if ($qs)
		{
			parse_str($qs, $parsed_query_string);

			$captured['__query__'] = $parsed_query_string;
		}

		return new Route
		(
			$pattern, $route + array
			(
				'id' => $id
			)
		);
	}
}