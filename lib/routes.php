<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Prototype\MethodNotDefined;
use ICanBoogie\Routing\Pattern;

/**
 * The route collection.
 *
 * Initial routes are collected from the "routes" config.
 *
 *
 *
 * Event: ICanBoogie\Routes::collect:before
 * ----------------------------------------
 *
 * Third parties may use the event {@link Routes\BeforeCollectEvent} to alter the configuration
 * fragments before they are synthesized. The event is fired during {@link __construct()}.
 *
 *
 *
 * Event: ICanBoogie\Routes::collect
 * ---------------------------------
 *
 * Third parties may use the event {@link Routes\CollectEvent} to alter the routes read from
 * the configuration. The event is fired during {@link __construct()}.
 */
class Routes implements \IteratorAggregate, \ArrayAccess
{
	static protected $instance;

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return \ICanBoogie\Routes
	 */
	static public function get()
	{
		if (!self::$instance)
		{
			self::$instance = new static();
		}

		# patch to support $routes->get('/', ... )

		if (func_num_args() > 1)
		{
			return self::$instance->__call('get', func_get_args());
		}

		return self::$instance;
	}

	protected $routes = array();

	protected $instances = array();

	protected $default_route_class = 'ICanBoogie\Route';

	/**
	 * Collects routes definitions from the "routes" config.
	 */
	protected function __construct()
	{
		$this->routes = $this->collect();
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

		$class = $this->default_route_class;

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
	 *
	 * @see ArrayAccess::offsetSet()
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
	 * Returns route collection.
	 *
	 * The collection is built in 4 steps:
	 *
	 * 1. Routes are traversed to add the `module` and `via` properties. If the route is defined
	 * by a module the `module` property is set to the id of the module, otherwise it is set
	 * to `null`. The `via` property is set to {@link Request::METHOD_ANY} if it is not defined.
	 *
	 * 2. The {@link Routes\BeforeCollectEvent} event is fired.
	 *
	 * @return array
	 */
	protected function collect()
	{
		global $core;

		// TODO-20121119: all of this should be outside the class, in a configurator

		if (!isset($core))
		{
			return array();
		}

		$collection = $this;

		return $core->configs->synthesize
		(
			'routes', function($fragments) use($collection)
			{
				global $core;

				$module_roots = array();

				foreach ($core->modules->descriptors as $module_id => $descriptor)
				{
					$module_roots[$descriptor[Module::T_PATH]] = $module_id;
				}

				foreach ($fragments as $module_root => &$fragment)
				{
					$module_root = dirname(dirname($module_root)) . DIRECTORY_SEPARATOR;
					$module_id = isset($module_roots[$module_root]) ? $module_roots[$module_root] : null;

					foreach ($fragment as $route_id => &$route)
					{
						$route += array
						(
							'via' => Request::METHOD_ANY,
							'module' => $module_id
						);
					}
				}

				unset($fragment);
				unset($route);

				new Routes\BeforeCollectEvent($collection, array('fragments' => &$fragments));

				$routes = array();

				foreach ($fragments as $fragment)
				{
					foreach ($fragment as $id => $route)
					{
						$routes[$id] = $route + array
						(
							'pattern' => null
						);
					}
				}

				new Routes\CollectEvent($collection, array('routes' => &$routes));

				return $routes;
			}
		);
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

/*
 * EXCEPTIONS
 */

/**
 * Exception thrown when a route does not exists.
 *
 * @property-read string $id The identifier of the route.
 */
class RouteNotDefined extends \Exception
{
	private $id;

	/**
	 * @param string $id Identifier of the route.
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct($id, $code=404, \Exception $previous=null)
	{
		$this->id = $id;

		parent::__construct("The route <q>$id</q> is not defined.", $code, $previous);
	}

	public function __get($property)
	{
		if ($property == 'id')
		{
			return $this->id;
		}

		throw new PropertyNotDefined(array($property, $this));
	}
}

/*
 * EVENTS
 */

namespace ICanBoogie\Routes;

/**
 * Event class for the `ICanBoogie\Events::collect:before` event.
 *
 * Third parties may use this event to alter the configuration fragments before they are
 * synthesized.
 */
class BeforeCollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the configuration fragments.
	 *
	 * @var array
	 */
	public $fragments;

	/**
	 * The event is constructed with the type `alter:before`.
	 *
	 * @param \ICanBoogie\Routes $target The routes collection.
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Routes $target, array $payload)
	{
		parent::__construct($target, 'collect:before', $payload);
	}
}

/**
 * Event class for the `ICanBoogie\Events::collect` event.
 *
 * Third parties may use this event to alter the routes read from the configuration.
 */
class CollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the routes.
	 *
	 * @var array[string]array
	 */
	public $routes;

	/**
	 * The event is constructed with the type `collect`.
	 *
	 * @param \ICanboogie\Routes $target The routes collection.
	 * @param array $payload
	 */
	public function __construct(\ICanboogie\Routes $target, array $payload)
	{
		parent::__construct($target, 'collect', $payload);
	}
}