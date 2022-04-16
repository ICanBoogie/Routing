<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\Exception\NoResponder;
use ICanBoogie\HTTP\MethodNotAllowed;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\RequestResponderProvider;
use ICanBoogie\Routing\ResponderProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByUri;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Throwable;

use function uniqid;

final class RequestResponderProviderTest extends TestCase
{
	use ProphecyTrait;

	/**
	 * @var ObjectProphecy<RouteProvider>
	 */
	private ObjectProphecy $routes;

	/**
	 * @var ObjectProphecy<ResponderProvider>
	 */
	private ObjectProphecy $responders;
	private Request $request;

	protected function setUp(): void
	{
		parent::setUp();

		$this->routes = $this->prophesize(RouteProvider::class);
		$this->responders = $this->prophesize(ResponderProvider::class);
		$this->request = Request::from([
			Request::OPTION_URI => '/' . uniqid(),
			Request::OPTION_METHOD => RequestMethod::METHOD_POST,
		]);
	}

	public function test_unsupported_method(): void
	{
		$request = $this->request;

		$this->routes->route_for_predicate(new ByUri($request->uri, $request->method))
			->willReturn(null);
		$this->routes->route_for_predicate(new ByUri($request->uri))
			->willReturn(new Route('/', 'action'));

		$stu = $this->makeSTU();

		$this->expectException(MethodNotAllowed::class);
		$this->expectExceptionMessage("Method not allowed: POST.");

		$stu->responder_for_request($request);
	}

	public function test_route_not_found(): void
	{
		$request = $this->request;

		$this->routes->route_for_predicate(new ByUri($request->uri, $request->method))
			->willReturn(null);
		$this->routes->route_for_predicate(new ByUri($request->uri))
			->willReturn(null);

		$stu = $this->makeSTU();

		$this->assertNull($stu->responder_for_request($this->request));
	}

	public function test_no_responder_for_route(): void
	{
		$request = $this->request;

		$this->routes->route_for_predicate(new ByUri($request->uri, $request->method))
			->willReturn(new Route('/', $action = 'articles:create'));

		$this->responders->responder_for_action($action)
			->willReturn(null);

		$this->expectException(NoResponder::class);
		$this->expectExceptionMessage("No responder for action: articles:create.");

		$this->makeSTU()->responder_for_request($this->request);
	}

	/**
	 * @throws Throwable
	 */
	public function test_returns_responder(): void
	{
		$request = $this->request;
		$response = new Response();
		$route = new Route('/', $action = 'articles:create');
		$path_params = [ 'id' => uniqid() ];

		$responder = $this->prophesize(Responder::class);
		$responder->respond(
			Argument::that(function (Request $r) use ($request, $route, $path_params): bool {
				$this->assertSame($request, $r);
				$this->assertSame($route, $r->context->get(Route::class));
				$this->assertSame($path_params, $r->path_params);
				$this->assertSame($path_params, $r->params);
				return true;
			})
		)->willReturn($response);

		$this->routes->route_for_predicate(
			Argument::that(function (ByUri $predicate) use ($path_params): bool {
				$predicate->path_params = $path_params;

				return true;
			})
		)
			->willReturn($route);

		$this->responders->responder_for_action($action)
			->willReturn($responder);

		$r = $this->makeSTU()->responder_for_request($this->request);

		$this->assertSame($response, $r?->respond($request));
	}

	private function makeSTU(): \ICanBoogie\HTTP\ResponderProvider
	{
		return new RequestResponderProvider(
			$this->routes->reveal(),
			$this->responders->reveal(),
		);
	}
}
