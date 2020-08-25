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
use Throwable;

/**
 * Event class for the `ICanBoogie\Routing\RouteDispatcher::rescue` event.
 *
 * Event hooks may use this event to _rescue_ a route by providing a suitable response, or
 * replace the exception to throw if the rescue fails.
 *
 * @property Throwable $exception
 * @property-read Request $request
 * @property Response|null $response
 */
class RescueEvent extends Event
{
	public const TYPE = 'rescue';

	/**
	 * Reference to the exception to throw if the rescue fails.
	 *
	 * @var Throwable
	 *
	 * @uses get_exception
	 * @uses set_exception
	 */
	private $exception;

	protected function get_exception(): Throwable
	{
		return $this->exception;
	}

	protected function set_exception(Throwable $exception): void
	{
		$this->exception = $exception;
	}

	/**
	 * The request.
	 *
	 * @var Request
	 */
	private $request;

	protected function get_request(): Request
	{
		return $this->request;
	}

	/**
	 * Reference to the response that rescue the route.
	 *
	 * @var Response|null
	 */
	private $response;

	protected function get_response(): ?Response
	{
		return $this->response;
	}

	protected function set_response(?Response $response): void
	{
		$this->response = $response;
	}

	public function __construct(Route $target, Throwable &$exception, Request $request, ?Response &$response)
	{
		$this->exception = &$exception;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, self::TYPE);
	}
}
