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
 * Event class for the `ICanBoogie\Routing\Dispatcher::dispatch` event.
 *
 * Third parties may use this event to alter the response before it is returned by the dispatcher.
 */
class DispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The route.
	 *
	 * @var Route
	 */
	public $route;

	/**
	 * The request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * Reference to the response.
	 *
	 * @var Response|null
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param Dispatcher $target
	 * @param Route $route
	 * @param Request $request
	 * @param mixed $response
	 */
	public function __construct(Dispatcher $target, Route $route, Request $request, &$response)
	{
		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'dispatch');
	}
}
