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

use ICanBoogie\Route;
use ICanBoogie\Routes;

/**
 * Dispatches requests among the defined routes.
 *
 * If a route matching the request is found, the `$route` and `$decontextualized_path`
 * properties are add to the request object. `$route` holds the route object,
 * `$decontextualized_path` holds the decontextualized path. The path is decontextualized using
 * the {@link \ICanBoogie\Routing\decontextualized()} function.
 *
 * <pre>
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $dispatcher = new Dispatcher(array('routes' => 'ICanBoogie\Routing\Dispatcher'));
 * </pre>
 */
class Dispatcher implements \ICanBoogie\HTTP\IDispatcher
{
	public function __invoke(Request $request)
	{
		$path = rtrim(\ICanBoogie\Routing\decontextualize($request->normalized_path), '/');

		#
		# we trim ending '/' but we leave it for the index.
		#

		if (!$path)
		{
			$path = '/';
		}

		$route = Routes::get()->find($path, $captured, $request->method);

		if (!$route)
		{
			return;
		}

		if ($route->location)
		{
			return new RedirectResponse(\ICanBoogie\Routing\contextualize($route->location), 302);
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;
		$request->route = $route;
		$request->decontextualized_path = $path;

		return $this->dispatch($route, $request);
	}

	/**
	 * Dispatches the route.
	 *
	 * @param Route $route
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|null
	 */
	protected function dispatch(Route $route, Request $request)
	{
		new Dispatcher\BeforeDispatchEvent($this, $route, $request, $response);

		if (!$response)
		{
			$controller = $route->controller;

			#
			# if the controller is not a callable then it is considred as a class name and use to
			# instantiate the controller.
			#

			if (!is_callable($controller))
			{
				$controller_class = $controller;
				$controller = new $controller_class($route);
			}

			$response = $controller($request);

			if ($response !== null && !($response instanceof Response))
			{
				$response = new Response($response, 200, array
				(
					'Content-Type' => 'text/html; charset=utf-8'
				));
			}
		}

		new Dispatcher\DispatchEvent($this, $route, $request, $response);

		return $response;
	}

	/**
	 * Rethrows the exception than was thrown during dispatch.
	 */
	public function rescue(\Exception $exception, Request $request)
	{
		throw $exception;
	}
}

/*
 * Events
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Route;

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::dispatch:before` event.
 *
 * Third parties may use this event to provide a response to the request before the route is
 * mapped. The event is usually used by third parties to redirect requests or provide cached
 * responses.
 */
class BeforeDispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The route.
	 *
	 * @var \ICanBoogie\Route
	 */
	public $route;

	/**
	 * The HTTP request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * Reference to the HTTP response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch:before`.
	 *
	 * @param Dispatcher $target
	 * @param array $payload
	 */
	public function __construct(Dispatcher $target, Route $route, Request $request, &$response)
	{
		if ($response !== null && !($response instanceof Response))
		{
			throw new \InvalidArgumentException('$response must be an instance of ICanBoogie\HTTP\Response. Given: ' . get_class($response) . '.');
		}

		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'dispatch:before');
	}
}

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::dispatch` event.
 *
 * Third parties may use this event to alter the response before it is returned by the dispatcher.
 */
class DispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The route.
	 *
	 * @var \ICanBoogie\Route
	 */
	public $route;

	/**
	 * The request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * Reference to the response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param Dispatcher $target
	 * @param array $payload
	 */
	public function __construct(Dispatcher $target, Route $route, Request $request, Response &$response)
	{
		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'dispatch');
	}
}