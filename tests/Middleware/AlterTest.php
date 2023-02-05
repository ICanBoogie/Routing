<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\Middleware;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware\Alter;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\Route\BeforeRespondEvent;
use ICanBoogie\Routing\Route\RespondEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\get_events;

final class AlterTest extends TestCase
{
    private MockObject&Responder $next;
    private Request $request;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();

        $route = new Route("/", "article:show");

        $this->next = $this->createMock(Responder::class);
        $this->request = Request::from();
        $this->request->context->add($route);
        $this->response = new Response();

        EventCollectionProvider::define(function () {
            static $events;

            return $events ??= new EventCollection();
        });
    }

    public function test_response_from_next(): void
    {
        $this->next
            ->method('respond')
            ->with($request = $this->request)
            ->willReturn($response = $this->response);

        $this->assertSame($response, $this->respond($request));
    }

    public function test_response_from_before_event(): void
    {
        $this->next
            ->expects($this->never())
            ->method('respond')
            ->with($this->anything());

        get_events()->attach(function (BeforeRespondEvent $event, Route $sender) {
            $event->response = $this->response;
        });

        $this->assertSame($this->response, $this->respond($this->request));
    }

    public function test_response_from_after_event(): void
    {
        $this->next
            ->method('respond')
            ->with($request = $this->request)
            ->willReturn($this->response);

        $new_response = new Response();

        get_events()->attach(function (RespondEvent $event, Route $sender) use ($new_response) {
            $event->response = $new_response;
        });

        $this->assertSame($new_response, $this->respond($request));
    }

    private function respond(Request $request): Response
    {
        return (new Alter())
            ->responder($this->next)
            ->respond($request);
    }
}
