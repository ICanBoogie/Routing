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
 * Event class for the `ICanBoogie\Routing\Route::rescue` event.
 *
 * Event hooks may use this event to _rescue_ a respond by providing a suitable response, or
 * replace the exception to throw if the rescue fails.
 */
final class RescueEvent extends Event
{
	public const TYPE = 'rescue';

	/**
	 * Reference to the exception to throw if the rescue fails.
	 */
	public Throwable $exception;

	/**
	 * Reference to the response that rescue the respond.
	 */
	public ?Response $response;

	public function __construct(
		Route $target,
		public readonly Request $request,
		Throwable &$exception,
		?Response &$response
	) {
		$this->exception = &$exception;
		$this->response = &$response;

		parent::__construct($target, self::TYPE);
	}
}
