<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Responder\RouteResponder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class RouterTest extends TestCase
{
	use ProphecyTrait;

	/**
	 * @dataProvider provide_method
	 */
	public function test_method(string $method, string $http_method): void
	{
		$response = new Response();
		$pattern = '/articles/<\d+>';
		$closure = function (Request $request) use ($response): Response {
			return $response;
		};

		$routes = new RouteCollection();
		$responders = new ResponderProvider\Mutable();

		$router = new Router($routes, $responders);
		$router->$method($pattern, $closure);

		$this->assertInstanceOf(Route::class,
			$route = $routes->route_for_uri(
				"/articles/123",
				$http_method
			));
		$this->assertInstanceOf(Responder::class, $responder = $responders->responder_for_action($route->action));
		$this->assertSame($response, $responder->respond(Request::from()));
	}

	public function provide_method(): array
	{
		return [

			[ 'any', Request::METHOD_ANY ],
			[ 'connect', Request::METHOD_CONNECT ],
			[ 'delete', Request::METHOD_DELETE ],
			[ 'get', Request::METHOD_GET ],
			[ 'head', Request::METHOD_HEAD ],
			[ 'options', Request::METHOD_OPTIONS ],
			[ 'patch', Request::METHOD_PATCH ],
			[ 'post', Request::METHOD_POST ],
			[ 'put', Request::METHOD_PUT ],
			[ 'trace', Request::METHOD_TRACE ],

		];
	}

	public function test_route(): void
	{
		$routes = new RouteCollection();
		$responders = new ResponderProvider\Mutable();
		$response = new Response();

		(new Router($routes, $responders))
			->get('/articles/<id:\d+>',
				function (Request $request) use ($response): Response {
					$id = $request->path_params['id'];

					$this->assertSame('123', $id);

					return $response;
				})
			->get('/it-s-a-trap',
				function (Request $request): Response {
					$this->fail("should not be called");
				});

		$responder = new RouteResponder($routes, $responders);
		$actual = $responder->respond(Request::from([ Request::OPTION_PATH => '/articles/123' ]));

		$this->assertSame($response, $actual);
	}
}
