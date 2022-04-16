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

use Closure;
use ICanBoogie\Accessor\AccessorTrait;
use ICanBoogie\HTTP\Dispatcher;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Routing\Route\BeforeRespondEvent;
use ICanBoogie\Routing\Route\RespondEvent;
use ICanBoogie\Routing\Route\RescueEvent;
use Throwable;

use function is_callable;
use function rtrim;

/**
 * Dispatch requests among the defined routes.
 *
 * If a route matching the request is found, the `$route` and `$decontextualized_path`
 * properties are added to the {@link Request} instance. `$route` holds the {@link Route} instance,
 * `$decontextualized_path` holds the decontextualized path. The path is decontextualized using
 * the {@link decontextualize()} function.
 *
 * @deprecated
 */
class RouteDispatcher implements Dispatcher
{
	/**
	 * @uses get_routes
	 */
	use AccessorTrait;

	public function __construct(
		private readonly Responder $responder
	) {
	}

	public function __invoke(Request $request): ?Response
	{
		$normalized_path = $this->normalize_path($request->normalized_path);

		$request->decontextualized_path = $normalized_path;

		return $this->responder->respond($request);
	}

	/**
	 * Normalizes request path.
	 *
	 * @return string Decontextualized path with trimmed ending slash.
	 */
	protected function normalize_path(string $path): string
	{
		$normalized_path = $path;

		if ($normalized_path != '/') {
			$normalized_path = rtrim($normalized_path, '/');
		}

		return $normalized_path;
	}

	/**
	 * Alters request context with route and controller.
	 */
	protected function alter_context(Request\Context $context, Route $route, callable $controller): void
	{
		$context->route = $route;
		$context->controller = $controller;
	}

	/**
	 * Fires {@link \ICanBoogie\Routing\RouteDispatcher\RescueEvent} and returns the response provided
	 * by third parties. If no response was provided, the exception (or the exception provided by
	 * third parties) is re-thrown.
	 *
	 * @throws Throwable if the exception cannot be rescued.
	 */
	public function rescue(Throwable $exception, Request $request): Response
	{
		if (isset($request->context->route)) {
			$response = null;

			new RescueEvent($request->context->route, $request, $exception, $response);

			if ($response) {
				return $response;
			}
		}

		throw $exception;
	}

	/**
	 * @param callable|string $controller
	 */
	private function resolve_controller($controller): Responder
	{
		if ($controller instanceof Closure) {
			return new ResponderFunc($controller);
		}

		if (is_callable($controller)) {
			return new ResponderFunc(function () use ($controller) {
				/* @var $this ResponderFunc */

				return $controller($this->request);
			});
		}

		return new $controller;
	}
}
