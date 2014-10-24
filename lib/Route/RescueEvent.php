<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Route;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Routing\Exception;
use ICanBoogie\Routing\Route;

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::rescue` event.
 *
 * Event hooks may use this event to _rescue_ a route by providing a suitable response, or
 * replace the exception to throw if the rescue fails.
 */
class RescueEvent extends \ICanBoogie\Event implements Exception
{
	/**
	 * Reference to the exception to throw if the rescue fails.
	 *
	 * @var \Exception
	 */
	public $exception;

	/**
	 * The request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * Reference to the response that rescue the route.
	 *
	 * @var Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `rescue`.
	 *
	 * @param Route $route
	 * @param \Exception $target Reference to the exception thrown while dispatching the route.
	 * @param Request $request
	 * @param Response|null $response
	 */
	public function __construct(Route $target, \Exception &$exception, Request $request, &$response)
	{
		$this->exception = &$exception;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, 'rescue');
	}
}
