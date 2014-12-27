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
use ICanBoogie\Object;
use ICanBoogie\PropertyNotDefined;

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
 * @property-read \ICanBoogie\Core $app The application.
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
	 * The route to control.
	 *
	 * @var Route
	 */
	protected $route;

	/**
	 * Initializes the {@link $route} property.
	 *
	 * @param Route $route The route to control.
	 */
	public function __construct(Route $route)
	{
		$this->route = $route;
	}

	/**
	 * Controls the route and returns a response.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response
	 */
	abstract public function __invoke(Request $request);

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
	 * @param string $url The URL to redirect the request to.
	 * @param int $status Status code (defaults to 302).
	 * @param array $headers Additional headers.
	 *
	 * @return RedirectResponse
	 */
	public function redirect($url, $status=302, array $headers=[])
	{
		return new RedirectResponse($url, $status, $headers);
	}
}
