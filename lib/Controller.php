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
use ICanBoogie\Routing\Controller\BeforeRespondEvent;
use ICanBoogie\Routing\Controller\RespondEvent;

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
 * @property Response $response.
 * @property-read \ICanBoogie\Core $app The application.
 *
 * @property-read \ICanBoogie\Module $module The module defining the route. (This getter is
 * provided by the icanboogie/module package)
 * @property-read \ICanBoogie\ActiveRecord\Model $model The primary model of the module. (This
 * getter is provided by the icanboogie/module package)
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

	protected function lazy_get_response()
	{
		return new Response;
	}

	/**
	 * Controls the route and returns a response.
	 *
	 * The response is obtained by invoking `respond()`, when the result is a {@link Response}
	 * instance it is returned as is, when the `$response` property has been initialized the result
	 * is used as its body and the response is returned, otherwise the result is returned as is.
	 *
	 * The `ICanBoogie\Routing\Controller::respond:before` event of class
	 * {@link Controller\BeforeRespondEvent} is fired before invoking `respond()`, the
	 * `ICanBoogie\Routing\Controller::respond:before` event of class
	 * {@link Controller\RespondEvent} is fired after.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|mixed
	 */
	final public function __invoke(Request $request)
	{
		$this->request = $request;

		$response = null;

		new BeforeRespondEvent($this, $response);

		if (!$response)
		{
			$response = $this->respond($request);
		}

		new RespondEvent($this, $response);

		if ($response instanceof Response)
		{
			return $response;
		}

		if ($response && isset($this->response))
		{
			$this->response->body = $response;

			return $this->response;
		}

		return $response;
	}

	/**
	 * Respond to the request.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|mixed
	 */
	abstract protected function respond(Request $request);

	/**
	 * Tries to get the undefined property from the application.
	 *
	 * @param string $property
	 * @param bool $success
	 *
	 * @return mixed
	 */
	public function last_chance_get($property, &$success)
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
	public function redirect($url, $status=302, array $headers=[])
	{
		if ($url instanceof Route)
		{
			$url = $url->url;
		}

		return new RedirectResponse($url, $status, $headers);
	}
}
