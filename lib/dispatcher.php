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
 * the {@link decontextualize()} function.
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
		$path = rtrim(decontextualize($request->normalized_path), '/');

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
			return new RedirectResponse(contextualize($route->location), 302);
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
	 * @return Response|null
	 */
	protected function dispatch(Route $route, Request $request)
	{
		new Dispatcher\BeforeDispatchEvent($this, $route, $request, $response);

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

		new Dispatcher\DispatchEvent($this, $route, $request, $response);

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
			new Dispatcher\RescueEvent($exception, $request, $request->route, $response);

			if ($response)
			{
				return $response;
			}
		}

		throw $exception;
	}
}

/*
 * Events
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Route;
use ICanBoogie\Routing\Dispatcher;

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
	 * @var \ICanBoogie\HTTP\Response|null
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param Dispatcher $target
	 * @param array $payload
	 */
	public function __construct(Dispatcher $target, Route $route, Request $request, &$response)
	{
		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'dispatch');
	}
}

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::rescue` event.
 *
 * Third parties may use this event to _rescue_ an exception by providing a suitable response.
 * Third parties may also use this event to replace the exception to rethrow.
 */
class RescueEvent extends \ICanBoogie\Exception\RescueEvent
{
	/**
	 * Route to rescue.
	 *
	 * @var \ICanBoogie\Route
	 */
	public $route;

	/**
	 * Initializes the {@link $route} property.
	 *
	 * @param \Exception $target
	 * @param \ICanBoogie\HTTP\Request $request
	 * @param \ICanBoogie\Route $route
	 * @param \ICanBoogie\HTTP\Response|null $response
	 */
	public function __construct(\Exception &$target, Request $request, Route $route, &$response)
	{
		$this->route = $route;

		parent::__construct($target, $request, $response);
	}
}