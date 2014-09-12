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
use ICanBoogie\Routing\Dispatcher\BeforeDispatchEvent;
use ICanBoogie\Routing\Dispatcher\DispatchEvent;
use ICanBoogie\Routing\Dispatcher\RescueEvent;

/**
 * Dispatch requests among the defined routes.
 *
 * If a route matching the request is found, the `$route` and `$decontextualized_path`
 * properties are added to the {@link Request} instance. `$route` holds the {@link Route} instance,
 * `$decontextualized_path` holds the decontextualized path. The path is decontextualized using
 * the {@link decontextualize()} function.
 */
class Dispatcher implements \ICanBoogie\HTTP\IDispatcher
{
	/**
	 * Route collection.
	 *
	 * @var Routes
	 */
	protected $routes;

	public function __construct(Routes $routes=null)
	{
		// FIXME-20140912: we should be independant from the core, the way dispatcher are created should be enhanced
		if (!$routes && class_exists('ICanBoogie\Core'))
		{
			$routes = \ICanBoogie\Core::get()->routes;
		}

		$this->routes = $routes;
	}

	public function __invoke(Request $request)
	{
		$decontextualized_path = decontextualize($request->normalized_path);

		if ($decontextualized_path != '/')
		{
			$decontextualized_path = rtrim($decontextualized_path, '/');
		}

		$route = $this->routes->find($decontextualized_path, $captured, $request->method);

		if (!$route)
		{
			return;
		}

		if ($route->location)
		{
			return new RedirectResponse(contextualize($route->location), 302);
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;
		$request->route = $route;
		$request->decontextualized_path = $decontextualized_path;

		return $this->dispatch($route, $request);
	}

	/**
	 * Dispatches the route.
	 *
	 * @param Route $route
	 * @param Request $request
	 *
	 * @return Response|null
	 */
	protected function dispatch(Route $route, Request $request)
	{
		new BeforeDispatchEvent($this, $route, $request, $response);

		if (!$response)
		{
			$controller = $route->controller;

			#
			# if the controller is not a callable then it is considered as a class name and
			# is used to instantiate the controller.
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

		new DispatchEvent($this, $route, $request, $response);

		return $response;
	}

	/**
	 * Fires {@link \ICanBoogie\Routing\Dispatcher\RescueEvent} and returns the response provided
	 * by third parties. If no response was provided, the exception (or the exception provided by
	 * third parties) is rethrown.
	 *
	 * @return \ICanBoogie\HTTP\Response
	 */
	public function rescue(\Exception $exception, Request $request)
	{
		if (isset($request->route))
		{
			new RescueEvent($exception, $request, $request->route, $response);

			if ($response)
			{
				return $response;
			}
		}

		throw $exception;
	}
}