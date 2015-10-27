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

use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;

/**
 * Event class for the `ICanBoogie\Routing\RouteDispatcher::rescue` event.
 *
 * Event hooks may use this event to _rescue_ a route by providing a suitable response, or
 * replace the exception to throw if the rescue fails.
 *
 * @property \Exception $exception
 * @property-read Request $request
 * @property Response|null $response
 */
class RescueEvent extends Event
{
	const TYPE = 'rescue';

	/**
	 * Reference to the exception to throw if the rescue fails.
	 *
	 * @var \Exception
	 */
	private $exception;

	protected function get_exception()
	{
		return $this->exception;
	}

	protected function set_exception(\Exception $exception)
	{
		return $this->exception = $exception;
	}

	/**
	 * The request.
	 *
	 * @var Request
	 */
	private $request;

	protected function get_request()
	{
		return $this->request;
	}

	/**
	 * Reference to the response that rescue the route.
	 *
	 * @var Response
	 */
	private $response;

	protected function get_response()
	{
		return $this->response;
	}

	protected function set_response(Response $response = null)
	{
		$this->response = $response;
	}

	/**
	 * The event is constructed with the type `rescue`.
	 *
	 * @param Route $target
	 * @param \Exception $exception Reference to the exception thrown while dispatching the route.
	 * @param Request $request
	 * @param Response|null $response
	 */
	public function __construct(Route $target, \Exception &$exception, Request $request, &$response)
	{
		$this->exception = &$exception;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, self::TYPE);
	}
}
