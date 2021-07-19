<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;
use ICanBoogie\Routing\MiddlewareCollection;
use PHPUnit\Framework\TestCase;

final class WithMiddlewareTest extends TestCase
{
	public function test_responder_for_action()
	{
		$middleware = new MiddlewareCollection([
			$this->middleware("abc"),
			$this->middleware("def"),
			$this->middleware("ghi"),
		]);

		$responders = new WithMiddleware(
			,
			$middleware
		);
	}

	private function middleware(string $text): Middleware
	{
		return new class ($text) implements Middleware {
			public function __construct(private string $text)
			{
			}

			public function responder(Responder $next): Responder
			{
				return new class ($next, $this->text) implements Responder {
					public function __construct(
						private Responder $next,
						private string $text
					) {
					}

					public function respond(Request $request): Response
					{
						$response = $this->next->respond($request);
						$response->body .= $this->text;

						return $response;
					}
				};
			}
		};
	}
}
