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
use ICanBoogie\Object;
use ICanBoogie\PropertyNotDefined;
use ICanBoogie\Routing\Controller\BeforeActionEvent;
use ICanBoogie\Routing\Controller\ActionEvent;

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
 * @property-read \ICanBoogie\Core $app The application.
 *
 * @property-read \ICanBoogie\Module $module The module defining the route. (This getter is
 * provided by the icanboogie/module package)
 * @property-read \ICanBoogie\ActiveRecord\Model $model The primary model of the module. (This
 * getter is provided by the icanboogie/module package)
 * @property-read \ICanBoogie\Events $events Provided through prototype getters or through `$app`.
 */
abstract class Controller extends Object
{
	/**
	 * Return the name of the controller, extracted from its class name.
	 *
	 * @return string|null The underscored name of the controller, or `null` if it cannot be
	 * extracted.
	 */
	protected function get_name()
	{
		$controller_class = get_class($this);

		if (preg_match('/(\w+)Controller$/', $controller_class, $matches))
		{
			return \ICanBoogie\underscore($matches[1]);
		}

		return null;
	}

	/**
	 * @var Request
	 */
	private $request;

	protected function get_request()
	{
		return $this->request;
	}

	/**
	 * @return Route
	 */
	protected function get_route()
	{
		return $this->request->context->route;
	}

	/**
	 * @return Response
	 */
	protected function lazy_get_response()
	{
		return new Response;
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
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|mixed
	 */
	final public function __invoke(Request $request)
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

		if ($result && isset($this->response))
		{
			$this->response->body = $result;

			return $this->response;
		}

		return $result;
	}

	/**
	 * Performs the proper action for the request.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|mixed
	 */
	abstract protected function action(Request $request);

	/**
	 * Tries to get the undefined property from the application.
	 *
	 * @param string $property
	 * @param bool $success
	 *
	 * @return mixed
	 */
	protected function last_chance_get($property, &$success)
	{
		try
		{
			$value = $this->app->$property;
			$success = true;

			return $value;
		}
		catch (PropertyNotDefined $e)
		{
			// We don't mind that the property is not defined by the app
		}

		return parent::last_chance_get($property, $success);
	}

	/**
	 * Redirects the request.
	 *
	 * @param Route|string $url The URL to redirect the request to.
	 * @param int $status Status code (defaults to 302).
	 * @param array $headers Additional headers.
	 *
	 * @return RedirectResponse
	 */
	public function redirect($url, $status = 302, array $headers = [])
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
	public function forward_to($destination)
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

		throw new \InvalidArgumentException("Don't know how to forward to: $destination.");
	}

	/**
	 * Forwards dispatching to another router.
	 *
	 * @param Route $route
	 *
	 * @return Response|mixed
	 */
	protected function forward_to_route(Route $route)
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
