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

use ICanBoogie\Routing\Route\BeforeRespondEvent;
use ICanBoogie\Routing\Route\RespondEvent;

use function assert;

/**
 * Allows event listeners to provide a response in stead of the next responder, circumventing it, or to alter/process
 * the result response.
 */
final class AlterMiddleware implements Responder
{
	public function __construct(
		private Responder $next
	) {
	}

	public function respond(Request $request): Response
	{
		$route = $this->extract_route($request);

		new BeforeRespondEvent($route, $request, $response);

		if (!$response)
		{
			$response = $this->next->respond($request);
		}

		new RespondEvent($route, $request, $response);

		return $response;
	}

	private function extract_route(Request $request): Route
	{
		$route = $request->context->get(Route::class);

		assert($route instanceof Route);

		return $route;
	}
}
