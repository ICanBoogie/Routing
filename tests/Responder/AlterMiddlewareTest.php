<?php

namespace ICanBoogie\Routing\Responder;

use Exception;
use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\Route\BeforeRespondEvent;
use ICanBoogie\Routing\Route\RespondEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

use function ICanBoogie\get_events;

final class AlterMiddlewareTest extends TestCase
{
	use ProphecyTrait;

	private ObjectProphecy|Responder $next;
	private Request $request;
	private Response $response;
	private Exception $exception;

	protected function setUp(): void
	{
		parent::setUp();

		$route = new Route("/", "article:show");

		$this->next = $this->prophesize(Responder::class);
		$this->request = Request::from();
		$this->request->context->add($route);
		$this->response = new Response();
		$this->exception = new Exception();

		EventCollectionProvider::define(function () {
			static $events;

			return $events ??= new EventCollection();
		});
	}

	public function test_response_from_next(): void
	{
		$this->next->respond($request = $this->request)
			->willReturn($response = $this->response);

		$this->assertSame($response, $this->respond($request));
	}

	public function test_response_from_before_event(): void
	{
		$this->next->respond(Argument::any())
			->shouldNotBeCalled();

		get_events()->attach(function (BeforeRespondEvent $event, Route $target) {
			$event->response = $this->response;
		});

		$this->assertSame($this->response, $this->respond($this->request));
	}

	public function test_response_from_after_event(): void
	{
		$this->next->respond($request = $this->request)
			->willReturn($this->response);

		$new_response = new Response();

		get_events()->attach(function (RespondEvent $event, Route $target) use ($new_response) {
			$event->response = $new_response;
		});

		$this->assertSame($new_response, $this->respond($request));
	}

	private function respond(Request $request): Response
	{
		return (new AlterMiddleware($this->next->reveal()))->respond($request);
	}
}
