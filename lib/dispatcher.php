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

/**
 * Dispatches requests among the defined routes.
 *
 * <pre>
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $dispatcher = new Dispatcher(array('routes' => 'ICanBoogie\RouteDispatcher'));
 * </pre>
 */
class RouteDispatcher implements \ICanBoogie\HTTP\IDispatcher
{
	public function __invoke(Request $request)
	{
		$path = rtrim(Route::decontextualize($request->normalized_path), '/');

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
			return new RedirectResponse(Route::contextualize($route->location), 302);
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;

		return $this->dispatch($route, $request);
	}

	protected function dispatch(Route $route, Request $request)
	{
		$response = null;

		new RouteDispatcher\BeforeDispatchEvent($this, $route, $request, $response);

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

			$request->route = $route;

			$response = $controller($request);

			if ($response !== null && !($response instanceof Response))
			{
				$response = new Response($response, 200, array
				(
					'Content-Type' => 'text/html; charset=utf-8'
				));
			}
		}

		new RouteDispatcher\DispatchEvent($this, $route, $request, $response);

		return $response;
	}

	public function rescue(\Exception $exception, Request $request)
	{
		throw $exception;
	}
}

/*
 * Events
 */

namespace ICanBoogie\RouteDispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Route;
use ICanBoogie\RouteDispatcher as Dispatcher;

/**
 * Event class for the `ICanBoogie\RouteDispatcher::dispatch:before` event.
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
 * Event class for the `ICanBoogie\RouteDispatcher::dispatch` event.
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