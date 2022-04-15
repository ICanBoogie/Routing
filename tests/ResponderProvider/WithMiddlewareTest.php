<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;
use ICanBoogie\Routing\MiddlewareCollection;
use ICanBoogie\Routing\ResponderProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class WithMiddlewareTest extends TestCase
{
	/**
	 * @throws Throwable
	 */
	public function test_responder_for_action(): void
	{
		$responderProvider = new class() implements ResponderProvider {

			public function responder_for_action(string $action): ?Responder
			{
				return new class() implements Responder {
					public function respond(Request $request): Response
					{
						return new Response("m");
					}
				};
			}
		};

		$middleware = new MiddlewareCollection([
			$this->middleware("ad"),
			$this->middleware("on"),
			$this->middleware("na"),
		]);

		$responders = new WithMiddleware(
			$responderProvider,
			$middleware
		);

		$response = $responders
			->responder_for_action("do.something")
			?->respond(Request::from());

		$this->assertEquals("madonna", $response?->body);
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
