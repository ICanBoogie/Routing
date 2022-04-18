<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\Route;

use Exception;
use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\get_events;

final class RescueEventTest extends TestCase
{
    private Route $route;
    private Request $request;
    private Response $response;
    private Exception $exception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route('/', '/');
        $this->request = Request::from();
        $this->response = new Response();
        $this->exception = new Exception();

        EventCollectionProvider::define(function () {
            static $events;

            return $events ??= new EventCollection();
        });
    }

    public function test_event(): void
    {
        $event = new Route\RescueEvent(
            $this->route,
            $this->request,
            $this->exception,
            $response
        );

        $this->assertSame($this->route, $event->target);
        $this->assertSame($this->request, $event->request);
        $this->assertSame($this->exception, $event->exception);
        $this->assertNull($response);
        $this->assertNull($event->response);

        $event->response = $this->response;

        $this->assertSame($this->response, $response);
        $this->assertSame($this->response, $event->response);
    }

    public function test_listen(): void
    {
        get_events()->attach(function (Route\RescueEvent $event, Route $target) use (&$used): void {
            $event->response = $this->response;
        });

        new Route\RescueEvent($this->route, $this->request, $this->exception, $response);

        $this->assertSame($this->response, $response);
    }
}
