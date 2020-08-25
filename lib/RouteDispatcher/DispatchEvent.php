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
	public const TYPE = 'dispatch';

	/**
	 * @var Route
	 */
	private $route;

	protected function get_route(): Route
	{
		return $this->route;
	}

	/**
	 * @var Request
	 */
	private $request;

	protected function get_request(): Request
	{
		return $this->request;
	}

	/**
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

	public function __construct(RouteDispatcher $target, Route $route, Request $request, Response &$response = null)
	{
		$this->route = $route;
		$this->request = $request;
		$this->response = &$response;

		parent::__construct($target, self::TYPE);
	}
}
