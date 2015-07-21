<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\RouteDispatcher;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\Route;

/**
 * Event class for the `ICanBoogie\Routing\RouteDispatcher::dispatch` event.
 *
 * Third parties may use this event to alter the response before it is returned by the dispatcher.
 *
 * @property-read Route $route
 * @property-read Request $request
 * @property Response $response
 */
class DispatchEvent extends Event
{
	/**
	 * The route.
	 *
	 * @var Route
	 */
	private $route;

	protected function get_route()
	{
		return $this->route;
	}

	/**
	 * The HTTP request.
	 *
	 * @var Request
	 */
	private $request;

	protected function get_request()
	{
		return $this->request;
	}

	/**
	 * Reference to the HTTP response.
	 *
	 * @var Response
	 */
	private $response;

	protected function get_response()
	{
		return $this->response;
	}

	protected function set_response(Response &$response = null)
	{
		$this->response = $response;
	}

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param RouteDispatcher $target
	 * @param Route $route
	 * @param Request $request
	 * @param Response|null $response
	 */
	public function __construct(RouteDispatcher $target, Route $route, Request $request, &$response)
	{
		$this->route = $route;
		$this->request = $request;
		$this->set_response($response);

		parent::__construct($target, 'dispatch');
	}
}
