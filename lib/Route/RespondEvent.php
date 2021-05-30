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
 * Event class for the `ICanBoogie\Routing\Route::respond` event.
 *
 * Third parties may use this event to alter the response before it is returned by the dispatcher.
 *
 * @property-read Route $respond
 * @property-read Request $request
 * @property Response $response
 */
final class RespondEvent extends Event
{
	public const TYPE = 'respond';

	protected function get_request(): Request
	{
		return $this->request;
	}

	private ?Response $response;

	protected function get_response(): ?Response
	{
		return $this->response;
	}

	protected function set_response(?Response $response): void
	{
		$this->response = $response;
	}

	/**
	 * @uses get_request
	 * @uses get_response
	 * @uses set_response
	 */
	public function __construct(
		Route $target,
		private Request $request,
		Response &$response = null
	) {
		$this->response = &$response;

		parent::__construct($target, self::TYPE);
	}
}
