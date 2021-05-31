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

use ICanBoogie\HTTP\Exception\NoResponder;
use ICanBoogie\HTTP\MethodNotSupported;
use ICanBoogie\HTTP\NotFound;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ResponderProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

final class RouteResponderTest extends TestCase
{
	use ProphecyTrait;

	private ObjectProphecy|RouteProvider $routes;
	private ObjectProphecy|ResponderProvider $responders;
	private ObjectProphecy|Responder $responder;
	private Request $request;
	private Response $response;
	private Route $route;

	protected function setUp(): void
	{
		$this->routes = $this->prophesize(RouteProvider::class);
		$this->responders = $this->prophesize(ResponderProvider::class);
		$this->responder = $this->prophesize(Responder::class);
		$this->request = Request::from([
			Request::OPTION_METHOD => Request::METHOD_DELETE,
			Request::OPTION_URI => '/articles/123',
			Request::OPTION_PATH_PARAMS => [ 'path_param1' => 'val1' ],
		]);
		$this->response = new Response();
		$this->route = new Route('/articles/<\d+>', 'article:delete');
	}

	public function test_respond_no_route(): void
	{
		$path_params = null;

		$this->routes->route_for_uri('/articles/123', Request::METHOD_DELETE, $path_params)
			->willReturn(null);
		$this->routes->route_for_uri('/articles/123')
			->willReturn(null);

		$this->expectException(NotFound::class);

		$this->respond($this->request);
	}

	public function test_respond_no_route_but_any(): void
	{
		$path_params = null;

		$this->routes->route_for_uri('/articles/123', Request::METHOD_DELETE, $path_params)
			->willReturn(null);
		$this->routes->route_for_uri('/articles/123')
			->willReturn($this->route);

		$this->expectException(MethodNotSupported::class);

		$this->respond($this->request);
	}

	public function test_respond_no_responder(): void
	{
		$path_params = null;

		$this->routes->route_for_uri('/articles/123', Request::METHOD_DELETE, $path_params)
			->willReturn($this->route);
		$this->responders->responder_for_action('article:delete')
			->willReturn(null);

		$this->expectException(NoResponder::class);

		$this->respond($this->request);
	}

	public function test_respond_success(): void
	{
		$route = $this->route;
		$request = $this->request;
		$response = $this->response;

		// We need this monster because reference params is not working with Prophecy.
		$this->routes = new class($route) implements RouteProvider {
			public function __construct(
				private Route $route,
			) {
			}

			public function route_for_uri(
				string $uri,
				string $method = Request::METHOD_ANY,
				array &$path_params = null,
				array &$query_params = null
			): ?Route {
				$path_params = [ 'path_param2' => 'val2' ];

				return $this->route;
			}

			public function reveal(): self
			{
				return $this;
			}
		};

		$this->responders->responder_for_action('article:delete')
			->willReturn($this->responder);
		$this->responder->respond($request)
			->willReturn($response);

		$this->assertSame($response, $this->respond($request));
		$this->assertSame($route, $request->context->get(Route::class));
		$this->assertEquals([ 'path_param1' => 'val1', 'path_param2' => 'val2' ], $request->path_params);
		$this->assertEquals([ 'path_param1' => 'val1', 'path_param2' => 'val2' ], $request->params);
	}

	private function respond(Request $request): Response
	{
		return (new RouteResponder(
			$this->routes->reveal(),
			$this->responders->reveal(),
		))->respond($request);
	}
}
