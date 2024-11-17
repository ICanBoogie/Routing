<?php

namespace Test\ICanBoogie\Routing\Route;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;
use Throwable;

use function ICanBoogie\emit;

final class RespondEventTest extends TestCase
{
    private Route $route;
    private Request $request;
    private Response $response;
    private EventCollection $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route('/', '/');
        $this->request = Request::from();
        $this->response = new Response();
        $this->events = new EventCollection();

        EventCollectionProvider::define(fn() => $this->events);
    }

    /**
     * @throws Throwable
     */
    public function test_event(): void
    {
        $response = $this->response;

        $event = emit(new Route\RespondEvent(
            $this->route,
            $this->request,
            $response
        ));

        $this->assertSame($this->route, $event->sender);
        $this->assertSame($this->request, $event->request);
        $this->assertSame($this->response, $event->response);

        $event->response = $new_response = new Response();

        $this->assertSame($new_response, $response);
        $this->assertSame($new_response, $event->response);
    }

    /**
     * @throws Throwable
     */
    public function test_listen(): void
    {
        $this->events->attach(function (Route\RespondEvent $event, Route $sender) use (&$used): void {
            $used = true;
        });

        emit(new Route\RespondEvent($this->route, $this->request, $this->response));

        $this->assertTrue($used);
    }
}
