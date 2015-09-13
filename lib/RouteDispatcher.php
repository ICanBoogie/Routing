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

use ICanBoogie\Accessor\AccessorTrait;
use ICanBoogie\HTTP\Dispatcher;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\RouteDispatcher\BeforeDispatchEvent;
use ICanBoogie\Routing\RouteDispatcher\DispatchEvent;
use ICanBoogie\Routing\Route\RescueEvent;

/**
 * Dispatch requests among the defined routes.
 *
 * If a route matching the request is found, the `$route` and `$decontextualized_path`
 * properties are added to the {@link Request} instance. `$route` holds the {@link Route} instance,
 * `$decontextualized_path` holds the decontextualized path. The path is decontextualized using
 * the {@link decontextualize()} function.
 *
 * @property-read RouteCollection $routes
 */
class RouteDispatcher implements Dispatcher
{
	use AccessorTrait;

	/**
	 * Route collection.
	 *
	 * @var RouteCollection
	 */
	protected $routes;

	protected function get_routes()
	{
		return $this->routes;
	}

	/**
	 * @param RouteCollection|null $routes
	 */
	public function __construct(RouteCollection $routes = null)
	{
		$this->routes = $routes;
	}

	/**
	 * @param Request $request
	 *
	 * @return Response|null
	 */
	public function __invoke(Request $request)
	{
		$captured = [];
		$normalized_path = $this->normalize_path($request->normalized_path);
		$route = $this->resolve_route($request, $normalized_path, $captured);

		if (!$route)
		{
			return null;
		}

		if ($route->location)
		{
			return new RedirectResponse(contextualize($route->location), 302);
		}

		$this->alter_params($route, $request, $captured);

		$request->context->route = $route;
		$request->decontextualized_path = $normalized_path;

		return $this->dispatch($route, $request);
	}

	/**
	 * Normalizes request path.
	 *
	 * @param string $path
	 *
	 * @return string Decontextualized path with trimmed ending slash.
	 */
	protected function normalize_path($path)
	{
		$normalized_path = decontextualize($path);

		if ($normalized_path != '/')
		{
			$normalized_path = rtrim($normalized_path, '/');
		}

		return $normalized_path;
	}

	/**
	 * Resolves route from request.
	 *
	 * @param Request $request
	 * @param string $normalized_path
	 * @param array $captured
	 *
	 * @return false|Route|null
	 */
	protected function resolve_route(Request $request, $normalized_path, array &$captured)
	{
		return $this->routes->find($normalized_path, $captured, $request->method);
	}

	/**
	 * Alters request parameters.
	 *
	 * @param Route $route
	 * @param Request $request
	 * @param array $captured Parameters captured from the request's path.
	 */
	protected function alter_params(Route $route, Request $request, array $captured)
	{
		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;
	}

	/**
	 * Alters request context with route and controller.
	 *
	 * @param Request\Context $context
	 * @param Route $route
	 * @param callable $controller
	 */
	protected function alter_context(Request\Context $context, Route $route, callable $controller)
	{
		$context->route = $route;
		$context->controller = $controller;
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
		$response = null;

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

		if (!$controller instanceof Controller)
		{
			$controller_args = array_merge($controller_args, array_values($request->path_params));
		}

		$this->alter_context($request->context, $route, $controller);

		$response = call_user_func_array($controller, $controller_args);

		if ($response !== null && !$response instanceof Response)
		{
			$response = new Response($response, 200, [

				'Content-Type' => 'text/html; charset=utf-8'

			]);
		}

		return $response;
	}

	/**
	 * Fires {@link \ICanBoogie\Routing\RouteDispatcher\RescueEvent} and returns the response provided
	 * by third parties. If no response was provided, the exception (or the exception provided by
	 * third parties) is re-thrown.
	 *
	 * @param \Exception $exception The exception to rescue.
	 * @param Request $request The request being dispatched.
	 *
	 * @throws \Exception if the exception cannot be rescued.
	 *
	 * @return Response
	 */
	public function rescue(\Exception $exception, Request $request)
	{
		if (isset($request->context->route))
		{
			$response = null;

			new RescueEvent($request->context->route, $exception, $request, $response);

			if ($response)
			{
				return $response;
			}
		}

		throw $exception;
	}
}
