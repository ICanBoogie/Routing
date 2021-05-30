<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Responder;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\Route\RescueEvent;
use Throwable;

use function assert;

/**
 * Tries to rescue a failed response.
 *
 * {@link RescueEvent} is fired with the exception as target. If an event listener provides a response, it is returned,
 * otherwise the exception re-thrown.
 */
final class RescueMiddleware implements Responder
{
	public function __construct(
		private Responder $next
	) {
	}

	public function respond(Request $request): Response
	{
		try {
			return $this->next->respond($request);
		} catch (Throwable $e) {
			return $this->rescue($request, $e);
		}
	}

	/**
	 * @throws Throwable
	 */
	private function rescue(Request $request, Throwable $exception): Response
	{
		$route = $request->context->find(Route::class);

		if ($route)
		{
			assert($route instanceof Route);

			new RescueEvent($route, $request, $exception, $response);

			if ($response)
			{
				return $response;
			}
		}

		throw $exception;
	}
}
