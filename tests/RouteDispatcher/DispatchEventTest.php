<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\RouteDispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestDispatcher\DispatchEvent;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteDispatcher;
use PHPUnit\Framework\TestCase;
use TypeError;

class DispatchEventTest extends TestCase
{
    private $dispatcher;
    private $route;

    protected function setUp(): void
    {
        $this->markTestIncomplete();

        $this->dispatcher = $this
            ->getMockBuilder(RouteDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->route = $this
            ->getMockBuilder(Route::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function test_invalid_response_type()
    {
        /* @var $dispatcher RouteDispatcher */
        /* @var $route Route */

        $dispatcher = $this->dispatcher;
        $route = $this->route;
        $request = Request::from('/');

        $this->expectException(TypeError::class);

        DispatchEvent::from([

            'target' => $dispatcher,
            'route' => $route,
            'request' => $request,
            'response' => &$dispatcher

        ]);
    }

    public function test_response_reference()
    {
        /* @var $dispatcher RouteDispatcher */
        /* @var $route Route */

        $dispatcher = $this->dispatcher;
        $route = $this->route;
        $request = Request::from('/');
        $response = null;
        $expected_response = new Response();

        /* @var $event DispatchEvent */

        $event = DispatchEvent::from([

            'target' => $dispatcher,
            'route' => $route,
            'request' => $request,
            'response' => &$response

        ]);

        $this->assertSame($route, $event->route);
        $this->assertSame($request, $event->request);
        $this->assertNull($event->response);
        $event->response = $expected_response;
        $this->assertSame($expected_response, $event->response);
    }
}
