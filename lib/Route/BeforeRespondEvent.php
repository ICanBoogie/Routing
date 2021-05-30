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
 * Event class for the `ICanBoogie\Routing\Route::respond:before` event.
 *
 * Third parties may use this event to provide a response to the request before the route is
 * mapped. The event is usually used by third parties to redirect requests or provide cached
 * responses.
 *
 * @property-read Request $request
 * @property Response $response
 */
final class BeforeRespondEvent extends Event
{
	public const TYPE = 'respond:before';

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
	 * @uses set_response
	 * @uses get_response
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
