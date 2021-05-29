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
use ICanBoogie\HTTP\Status;
use ICanBoogie\Prototyped;
use ICanBoogie\Routing\Controller\ActionEvent;
use ICanBoogie\Routing\Controller\BeforeActionEvent;
use InvalidArgumentException;

use function get_class;
use function ICanBoogie\underscore;
use function is_array;
use function is_callable;
use function is_object;
use function json_encode;
use function preg_match;

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
 * @property-read string $name The name of the controller.
 * @property-read Request $request The request being dispatched.
 * @property-read Route $route The route being dispatched.
 * @property Response $response
 */
abstract class Controller extends Prototyped
{
	/**
	 * Return the name of the controller, extracted from its class name.
	 *
	 * @return string|null The underscored name of the controller, or `null` if it cannot be
	 * extracted.
	 */
	protected function get_name(): ?string
	{
		$controller_class = get_class($this);

		if (preg_match('/(\w+)Controller$/', $controller_class, $matches))
		{
			return underscore($matches[1]);
		}

		return null;
	}

	private Request $request;

	protected function get_request(): Request
	{
		return $this->request;
	}

	protected function get_route(): Route
	{
		return $this->request->context->route;
	}

	protected function lazy_get_response(): Response
	{
		return new Response(null, Status::OK, [

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
	 *
	 * @return Response|mixed
	 */
	final public function __invoke(Request $request) //TODO-202105: Only return Response
	{
		$this->request = $request;

		$result = null;

		new BeforeActionEvent($this, $result);

		if (!$result)
		{
			$result = $this->action($request);
		}

		new ActionEvent($this, $result);

		if ($result instanceof Response)
		{
			return $result;
		}

		if (isset($this->response))
		{
			if ($result !== null)
			{
				$this->response->body = $result;
			}

			return $this->response;
		}

		return $result;
	}

	/**
	 * Performs the proper action for the request.
	 *
	 * @return Response|mixed
	 */
	abstract protected function action(Request $request);

	/**
	 * Redirects the request.
	 *
	 * @param Route|string $url The URL to redirect the request to.
	 * @param int $status Status code (defaults to {@link Status::FOUND}, 302).
	 * @param array $headers Additional headers.
	 *
	 * @return RedirectResponse
	 */
	public function redirect(Route|string $url, int $status = Status::FOUND, array $headers = []): Response
	{
		if ($url instanceof Route)
		{
			$url = $url->url;
		}

		return new RedirectResponse($url, $status, $headers);
	}

	/**
	 * Forwards the request.
	 *
	 * @param Route|mixed $destination
	 *
	 * @return mixed
	 */
	public function forward_to(mixed $destination) //TODO-202105: Only support Route?
	{
		if ($destination instanceof Route)
		{
			return $this->forward_to_route($destination);
		}

		if (is_object($destination))
		{
			$destination = "instance of " . get_class($destination);
		}
		else if (is_array($destination))
		{
			$destination = json_encode($destination);
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
		$route->pattern->match($this->request->uri, $captured);

		$request = $this->request->with([

			'path_params' => $captured

		]);

		$request->context->route = $route;

		$controller = $route->controller;

		if (!is_callable($controller))
		{
			$controller = new $controller;
		}

		return $controller($request);
	}
}
