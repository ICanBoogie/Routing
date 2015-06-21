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
use ICanBoogie\Routing\Route\RescueEvent;

/**
 * Dispatch requests among the defined routes.
 *
 * If a route matching the request is found, the `$route` and `$decontextualized_path`
 * properties are added to the {@link Request} instance. `$route` holds the {@link Route} instance,
 * `$decontextualized_path` holds the decontextualized path. The path is decontextualized using
 * the {@link decontextualize()} function.
 */
class Dispatcher implements \ICanBoogie\HTTP\DispatcherInterface
{
	/**
	 * Route collection.
	 *
	 * @var RouteCollection
	 */
	protected $routes;

	public function __construct(RouteCollection $routes = null)
	{
		// @codeCoverageIgnoreStart
		// FIXME-20140912: we should be independent from the core, the way dispatcher are created should be enhanced
		if (!$routes && class_exists('ICanBoogie\Core'))
		{
			$routes = \ICanBoogie\app()->routes;
		}
		// @codeCoverageIgnoreEnd

		$this->routes = $routes;
	}

	/**
	 * @param Request $request
	 *
	 * @return Response|null
	 */
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
			return null;
		}

		if ($route->location)
		{
			return new RedirectResponse(contextualize($route->location), 302);
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;
		$request->context->route = $route;
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
			$response = $this->respond($route, $request);
		}

		new DispatchEvent($this, $route, $request, $response);

		return $response;
	}

	/**
	 * Returns a response for the route and request.
	 *
	 * If the controller's result is not `null` but is not in instance of {@link Response}, its
	 * result is wrapped in a {@link response} instance with the status code 200 and the
	 * `Content-Type` "text/html; charset=utf-8".
	 *
	 * @param Route $route
	 * @param Request $request
	 *
	 * @return Response|mixed
	 */
	protected function respond(Route $route, Request $request)
	{
		$controller = $route->controller;
		$controller_args = [ $request ];

		if (!is_callable($controller))
		{
			$controller = new $controller;
		}

		if (!($controller instanceof Controller))
		{
			$controller_args = array_merge($controller_args, array_values($request->path_params));
		}

		$response = call_user_func_array($controller, $controller_args);

		if ($response !== null && !($response instanceof Response))
		{
			$response = new Response($response, 200, [

				'Content-Type' => 'text/html; charset=utf-8'

			]);
		}

		return $response;
	}

	/**
	 * Fires {@link \ICanBoogie\Routing\Dispatcher\RescueEvent} and returns the response provided
	 * by third parties. If no response was provided, the exception (or the exception provided by
	 * third parties) is re-thrown.
	 *
	 * @param \Exception $exception The exception to rescue.
	 * @param Request $request The request being dispatched.
	 *
	 * @throws \Exception if the exception cannot be rescued.
	 *
	 * @return \ICanBoogie\HTTP\Response
	 */
	public function rescue(\Exception $exception, Request $request)
	{
		if (isset($request->context->route))
		{
			new RescueEvent($request->context->route, $exception, $request, $response);

			if ($response)
			{
				return $response;
			}
		}

		throw $exception;
	}
}
