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

use Exception;
use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\Route\RescueEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

use function ICanBoogie\get_events;

final class RescueMiddlewareTest extends TestCase
{
	use ProphecyTrait;

	private ObjectProphecy|Responder $next;
	private Request $request;
	private Response $response;
	private Exception $exception;
	private Route $route;

	protected function setUp(): void
	{
		parent::setUp();

		$this->next = $this->prophesize(Responder::class);
		$this->request = Request::from();
		$this->response = new Response();
		$this->exception = new Exception();
		$this->route = new Route("/", "article:show");

		EventCollectionProvider::define(function () {
			static $events;

			return $events ??= new EventCollection();
		});
	}

	public function test_return_next_response(): void
	{
		$this->next->respond($request = $this->request)
			->willReturn($response = $this->response);

		$this->assertSame($response, $this->respond($request));
	}

	public function test_failure_but_no_route_in_request_context(): void
	{
		$this->next->respond($request = $this->request)
			->willThrow($this->exception);

		$this->expectExceptionObject($this->exception);

		$this->respond($request);
	}

	public function test_rescue_replaces_exception(): void
	{
		$request = $this->request;
		$request->context->add($this->route);

		$this->next->respond($request)
			->willThrow($this->exception);

		$new_exception = new Exception();

		$this->expectExceptionObject($new_exception);

		get_events()->attach(function (RescueEvent $event, Route $target) use ($new_exception) {
			$event->exception = $new_exception;
		});

		$this->respond($request);
	}

	public function test_rescue_replaces_response(): void
	{
		$request = $this->request;
		$request->context->add($this->route);

		$this->next->respond($request)
			->willThrow($this->exception);

		get_events()->attach(function (RescueEvent $event, Route $target) {
			$event->response = $this->response;
		});

		$this->assertSame($this->response, $this->respond($request));
	}

	private function respond(Request $request): Response
	{
		return (new RescueMiddleware($this->next->reveal()))->respond($request);
	}
}
