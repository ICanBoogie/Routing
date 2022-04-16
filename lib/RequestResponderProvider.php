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

use ICanBoogie\HTTP\Exception\NoResponder;
use ICanBoogie\HTTP\MethodNotAllowed;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\ResponderProvider;
use ICanBoogie\HTTP\Response;

/**
 * Matches an HTTP request with a route responder.
 *
 * The route responder so that the following things happen when the `respond()` method is invoked:
 *
 * - The matching route is added to the request's context.
 * - The request's parameters are updated with the parameters extracted from the URI path.
 */
final class RequestResponderProvider implements ResponderProvider
{
	public function __construct(
		private readonly RouteProvider $routes,
		private readonly ActionResponderProvider $responders,
	) {
	}

	/**
	 * @throws MethodNotAllowed
	 */
	public function responder_for_request(Request $request): ?Responder
	{
		$method = $request->method;

		$route = $this->routes->route_for_predicate(
			$predicate = new RouteProvider\ByUri($request->uri, $method)
		);

		if (!$route) {
			// We try again the same URI but this time with request method ANY,
			// if we have a match then the method used is probably wrong.
			return $this->routes->route_for_predicate(new RouteProvider\ByUri($request->uri))
				? throw new MethodNotAllowed($method->value)
				: null;
		}

		$responder = $this->responders->responder_for_action($route->action)
			?? throw new NoResponder("No responder for action: $route->action.");

		return new class ($route, $predicate->path_params, $responder) implements Responder {
			/**
			 * @param array<int|string, string> $path_params
			 */
			public function __construct(
				private readonly Route $route,
				private readonly array $path_params,
				private readonly Responder $responder
			) {
			}

			public function respond(Request $request): Response
			{
				$request->context->add($this->route);
				$request->path_params += $this->path_params;
				$request->params += $this->path_params;

				return $this->responder->respond($request);
			}
		};
	}
}
