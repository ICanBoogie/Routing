<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Responder;

use ICanBoogie\HTTP\Exception\NoResponder;
use ICanBoogie\HTTP\MethodNotSupported;
use ICanBoogie\HTTP\NotFound;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ResponderProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Throwable;

/**
 * Respond to a request by first matching it to a route then that router to a responder.
 */
final class RouteResponder implements Responder
{
	public function __construct(
		private readonly RouteProvider $routes,
		private readonly ResponderProvider $responders
	) {
	}

	/**
	 * Tries to find the route matching the request, and tries to locate the responder matching the
	 * action of the route.
	 *
	 * If all goes well the route and the responder are added to the context of the request, the
	 * parameters of the request are updated with the parameters captured from the path info, and,
	 * finally, the request is forwarded to the responder.
	 *
	 * @throws MethodNotSupported if no matching route could be found with the request's method,
	 *     but one could be found for "any".
	 * @throws NotFound if no matching route could be found.
	 * @throws Throwable
	 */
	public function respond(Request $request): Response
	{
		$method = $request->method;

		$route = $this->routes->route_for_uri($request->uri, $method, $path_params);

		if (!$route) {
			$this->routes->route_for_uri($request->uri)
				? throw new MethodNotSupported($method->value)
				: throw new NotFound();
		}

		assert($route instanceof Route);

//		if ($route->location) {
//			return new RedirectResponse(contextualize($route->location), Status::FOUND);
//		}

		$responder = $this->responders->responder_for_action($route->action)
			?? throw new NoResponder("No responder for action: $route->action.");

		$request->context->add($route);
		$request->path_params += $path_params;
		$request->params += $path_params;

		return $responder->respond($request);
	}
}
