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

/**
 * A route controller.
 */
abstract class Controller extends Object
{
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
