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

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;

use Throwable;

use function ICanBoogie\emit;

final class BeforeRespondEventTest extends TestCase
{
    private Route $route;
    private Request $request;
    private EventCollection $events;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route('/', '/');
        $this->request = Request::from();
        $this->events = new EventCollection();

        EventCollectionProvider::define(fn() => $this->events);
    }

    /**
     * @throws Throwable
     */
    public function test_event(): void
    {
        $event = emit(new Route\BeforeRespondEvent(
            $this->route,
            $this->request,
            $response
        ));

        $this->assertSame($this->route, $event->target);
        $this->assertSame($this->request, $event->request);
        $this->assertNull($response);
        $this->assertNull($event->response);

        $event->response = $new_response = new Response();

        $this->assertSame($new_response, $response);
        $this->assertSame($new_response, $event->response);
    }

    /**
     * @throws Throwable
     */
    public function test_listen(): void
    {
        $this->events->attach(function (Route\BeforeRespondEvent $event, Route $target) use (&$used): void {
            $used = true;
        });

        emit(new Route\BeforeRespondEvent($this->route, $this->request));

        $this->assertTrue($used);
    }
}
