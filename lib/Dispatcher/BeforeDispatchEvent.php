<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Routing\Route;

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::dispatch:before` event.
 *
 * Third parties may use this event to provide a response to the request before the route is
 * mapped. The event is usually used by third parties to redirect requests or provide cached
 * responses.
 */
class BeforeDispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The route.
	 *
	 * @var Route
	 */
	public $route;

	/**
	 * The HTTP request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * Reference to the HTTP response.
	 *
	 * @var Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch:before`.
	 *
	 * @param Dispatcher $target
	 * @param Route $route
	 * @param Request $request
	 * @param mixed $response
	 */
	public function __construct(Dispatcher $target, Route $route, Request $request, &$response)
	{
		if ($response !== null && !($response instanceof Response))
		{
			throw new \InvalidArgumentException('$response must be an instance of ICanBoogie\HTTP\Response. Given: ' . get_class($response) . '.');
		}

		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'dispatch:before');
	}
}
