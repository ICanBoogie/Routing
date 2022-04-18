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
use ReflectionException;
use Throwable;

use function ICanBoogie\emit;

final class RescueEventTest extends TestCase
{
    private Route $route;
    private Request $request;
    private Response $response;
    private Exception $exception;
    private EventCollection $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route('/', '/');
        $this->request = Request::from();
        $this->response = new Response();
        $this->exception = new Exception();
        $this->events = new EventCollection();

        EventCollectionProvider::define(fn() => $this->events);
    }

    /**
     * @throws Throwable
     */
    public function test_event(): void
    {
        $event = emit(new Route\RescueEvent(
            $this->route,
            $this->request,
            $this->exception,
            $response
        ));

        $this->assertSame($this->route, $event->target);
        $this->assertSame($this->request, $event->request);
        $this->assertSame($this->exception, $event->exception);
        $this->assertNull($response);
        $this->assertNull($event->response);

        $event->response = $this->response;

        $this->assertSame($this->response, $response);
        $this->assertSame($this->response, $event->response);
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function test_listen(): void
    {
        $this->events->attach(function (Route\RescueEvent $event, Route $target): void {
            $event->response = $this->response;
        });

        emit(new Route\RescueEvent($this->route, $this->request, $this->exception, $response));

        $this->assertSame($this->response, $response);
    }
}
