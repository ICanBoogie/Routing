<?php

namespace ICanBoogie\Routing\Route;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\get_events;

final class RespondEventTest extends TestCase
{
	private Route $route;
	private Request $request;

	protected function setUp(): void
	{
		parent::setUp();

		$this->route = new Route('/', '/');
		$this->request = Request::from();

		EventCollectionProvider::define(function () {
			static $events;

			return $events ??= new EventCollection();
		});
	}

	public function test_event(): void
	{
		$event = new RespondEvent(
			$this->route,
			$this->request,
			$response
		);

		$this->assertSame($this->route, $event->target);
		$this->assertSame($this->request, $event->request);
		$this->assertNull($response);
		$this->assertNull($event->response);

		$event->response = $new_response = new Response();

		$this->assertSame($new_response, $response);
		$this->assertSame($new_response, $event->response);
	}

	public function test_listen(): void
	{
		get_events()->attach(function (RespondEvent $event, Route $target) use(&$used): void {
			$used = true;
		});

		new RespondEvent($this->route, $this->request);

		$this->assertTrue($used);
	}
}