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
 */
class RouteCollection implements \IteratorAggregate, \ArrayAccess
{
	const DEFAULT_ROUTE_CLASS = Route::class;

	static private $anonymous_id_count;

	static private function generate_anonymous_id()
	{
		return 'anonymous_route_' . ++self::$anonymous_id_count;
	}

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
			if (is_numeric($route_id))
			{
				$route_id = null;
			}

			$this[$route_id] = $route;
		}
	}

	public function __call($method, array $arguments)
	{
		$method = strtoupper($method);

		if ($method === Request::METHOD_ANY || in_array($method, Request::$methods))
		{
			list($pattern, $controller, $options) = $arguments + [ 2 => [] ];

			$definition = [

					'controller' => $controller,
					'pattern' => $pattern

			] + $options + [ 'via' => $method ];

			$this->add($definition);

			return $this;
		}

		throw new MethodNotDefined($method, $this);
	}

	protected function add(array $definition)
	{
		if (empty($definition['as']))
		{
			$definition['as'] = self::generate_anonymous_id();
		}

		$id = $definition['id'] = $definition['as'];

		unset($definition['as']);

		#

		if (empty($definition['pattern']))
		{
			throw new PatternNotDefined(\ICanBoogie\format("Route %id has no pattern. !route", [

				'id' => $id,
				'route' => $definition

			]));
		}

		if (empty($definition['controller']) && empty($definition['location']))
		{
			throw new ControllerNotDefined(\ICanBoogie\format("Route %id has no controller. !route", [

				'id' => $id,
				'route' => $definition

			]));
		}

		#
		# Separate controller class from its action.
		#

		if (isset($definition['controller']))
		{
			$controller = $definition['controller'];

			if (is_string($controller) && strpos($controller, '#'))
			{
				list($controller, $action) = explode('#', $controller);

				$definition['controller'] = $controller;
				$definition['action'] = $action;
			}
		}

		#

		$this->routes[$id] = $definition + [

			'via' => Request::METHOD_ANY

		];

		$this->revoke_cache();

		return $this;
	}

	/**
	 * Adds resource routes.
	 *
	 * **Note:** The route definitions for the resource are created by
	 * {@link RoutesMaker::resource}. Both methods accept the same arguments.
	 *
	 * @see \ICanBoogie\Routing\RoutesMaker::resource
	 *
	 * @param string $name
	 * @param string $controller
	 * @param array $options
	 *
	 * @return array
	 */
	public function resource($name, $controller, array $options = [])
	{
		$definitions = RoutesMaker::resource($name,$controller, $options);

		foreach ($definitions as $id => $definition)
		{
			$this[$id] = $definition;
		}
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

		return $this->instances[$id] = new $class($this, $properties['pattern'], $properties);
	}

	/**
	 * Define a route.
	 *
	 * @param string $id The identifier of the route.
	 * @param array $route The route definition.
	 */
	public function offsetSet($id, $route)
	{
		$this->add([ 'as' => $id ] + $route);
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
	 * @return Route|false|null
	 */
	public function find($uri, &$captured = null, $method = Request::METHOD_ANY, $namespace = null)
	{
		$captured = [];

		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		$parsed = (array) parse_url($uri) + [ 'path' => null, 'query' => null ];
		$path = $parsed['path'];

		if (!$path)
		{
			return false;
		}

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

			return null;
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

			return null;
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
