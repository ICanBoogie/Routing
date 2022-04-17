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
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\ResponseStatus;
use ICanBoogie\Prototyped;
use ICanBoogie\Routing\Controller\ActionEvent;
use ICanBoogie\Routing\Controller\BeforeActionEvent;
use InvalidArgumentException;
use JsonException;

use function get_class;
use function is_array;
use function is_callable;
use function is_object;
use function json_encode;
use function trigger_error;

use const E_USER_WARNING;
use const JSON_THROW_ON_ERROR;

/**
 * A route controller.
 *
 * # Accessing the application's properties
 *
 * The class tries to retrieve undefined properties from the application, so the following code
 * yields the same results:
 *
 * ```php
 * <?php
 *
 * $this->app->models
 * # or
 * $this->models
 * ```
 *
 * But because `request` is defined by the controller the following code might not yield the same
 * results:
 *
 * ```php
 * <?php
 *
 * $this->app->request
 * # or
 * $this->request
 * ```
 *
 * @property-read Request $request The request being dispatched.
 * @property-read Route $respond The route being dispatched.
 * @property Response $response
 */
abstract class ControllerAbstract extends Prototyped implements Responder
{
	private Request $request;

	protected function get_request(): Request
	{
		return $this->request;
	}

	protected function get_route(): Route
	{
		return $this->request->context->get(Route::class);
	}

	protected function lazy_get_response(): Response
	{
		return new Response(null, ResponseStatus::STATUS_OK, [

			'Content-Type' => 'text/html; charset=utf-8'

		]);
	}

	/**
	 * Controls the route and returns a response.
	 *
	 * The response is obtained by invoking `action()`. When the result is a {@link Response}
	 * instance it is returned as is, when the `$response` property has been initialized the result
	 * is used as its body and the response is returned, otherwise the result is returned as is.
	 *
	 * The `ICanBoogie\Routing\Controller::action:before` event of class
	 * {@link Controller\BeforeActionEvent} is fired before invoking `action()`, the
	 * `ICanBoogie\Routing\Controller::action:before` event of class
	 * {@link Controller\ActionEvent} is fired after.
	 */
	final public function respond(Request $request): Response
	{
		$this->request = $request;

		$result = null;

		new BeforeActionEvent($this, $result);

		if (!$result) {
			$result = $this->action($request);
		}

		new ActionEvent($this, $result);

		if ($result instanceof Response) {
			return $result;
		}

		if (isset($this->response)) {
			if ($result !== null) {
				$this->response->body = $result;
			}

			return $this->response;
		}

		return new Response($result);
	}

	/**
	 * Performs the proper action for the request.
	 *
	 * @return Response|mixed
	 */
	abstract protected function action(Request $request): mixed;

	/**
	 * Redirects the request.
	 *
	 * @param Route|string $url The URL to redirect the request to.
	 * @param int $status Status code (defaults to {@link ResponseStatus::STATUS_FOUND}, 302).
	 * @param array $headers Additional headers.
	 *
	 * @return RedirectResponse
	 */
	public function redirect(
		Route|string $url,
		int $status = ResponseStatus::STATUS_FOUND,
		array $headers = []
	): RedirectResponse {
		trigger_error("We need a URL generator here", E_USER_WARNING);

		if ($url instanceof Route) {
			$url = $url->url;
		}

		return new RedirectResponse($url, $status, $headers);
	}

	/**
	 * Forwards the request.
	 *
	 * @return mixed
	 * @throws JsonException
	 */
	public function forward_to(object|array|string $destination) //TODO-202105: Only support Route?
	{
		if ($destination instanceof Route) {
			return $this->forward_to_route($destination);
		}

		if (is_object($destination)) {
			$destination = "instance of " . get_class($destination);
		} elseif (is_array($destination)) {
			$destination = (string) json_encode($destination, JSON_THROW_ON_ERROR);
		}

		throw new InvalidArgumentException("Don't know how to forward to: $destination.");
	}

	/**
	 * Forwards dispatching to another router.
	 *
	 * @return Response|mixed
	 */
	protected function forward_to_route(Route $route) //TODO-202105: Return only Response
	{
		$route->pattern->matches($this->request->uri, $captured);

		$request = $this->request->with([

			'path_params' => $captured

		]);

		$request->context->route = $route;

		$controller = $route->controller;

		if (!is_callable($controller)) {
			$controller = new $controller();
		}

		return $controller($request);
	}
}
