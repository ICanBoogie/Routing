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

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\ControllerTest\MySampleController;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteDispatcher;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    /**
     * @var EventCollection
     */
    private $events;

    protected function setUp(): void
    {
        $this->markTestIncomplete();

        $this->events = $events = new EventCollection();

        EventCollectionProvider::define(function () use ($events) {
            return $events;
        });
    }

    public function test_should_get_name()
    {
        $controller = new MySampleController();
        $this->assertEquals('my_sample', $controller->name);
    }

    public function test_should_not_get_name()
    {
        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var $controller ControllerAbstract */

        $this->assertNull($controller->name);
    }

    public function test_lazy_get_response()
    {
        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var $controller ControllerAbstract */

        $response = $controller->response;

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($response, $controller->response);
    }

    public function test_invoke_should_return_response_from_action()
    {
        $request = Request::from('/');

        $response = new Response();

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'action' ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('action')
            ->willReturn($response);

        /* @var $controller ControllerAbstract */

        $this->assertSame($response, $controller->respond($request));
    }

    public function test_invoke_should_return_string()
    {
        $request = Request::from('/');

        $body = "some string" . uniqid();

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'action' ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('action')
            ->willReturn($body);

        /* @var $controller ControllerAbstract */

        $response = $controller->respond($request);
        $this->assertSame($body, $response);
    }

    public function test_invoke_should_return_string_in_response()
    {
        $request = Request::from('/');

        $body = "some string" . uniqid();

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'action' ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('action')
            ->willReturn($body);

        /* @var $controller ControllerAbstract */

        $response = $controller->response;
        $this->assertInstanceOf(Response::class, $response);
        $response2 = $controller->respond($request);
        $this->assertSame($response, $response2);
        $this->assertSame($body, $response2->body);
    }

    public function test_should_return_response_if_instantiated()
    {
        $request = Request::from('/');
        $status = Response::STATUS_NO_CONTENT;

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'action' ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('action')
            ->willReturnCallback(
                \Closure::bind(function () use ($status) {
                    /* @var ControllerAbstract $this */
                    $this->response->status = $status;
                }, $controller)
            );

        /* @var $controller ControllerAbstract */

        $response = $controller->respond($request);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame($status, $response->status->code);
    }

    public function test_redirect_to_path()
    {
        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $url = '/path/to/' . uniqid();

        /* @var $controller ControllerAbstract */
        /* @var $response RedirectResponse */

        $response = $controller->redirect($url);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($url, $response->location);
        $this->assertSame(302, $response->status->code);
    }

    public function test_redirect_to_route()
    {
        $url = '/path/to/' . uniqid();

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $route = $this
            ->getMockBuilder(Route::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'get_url' ])
            ->getMock();
        $route
            ->expects($this->once())
            ->method('get_url')
            ->willReturn($url);

        /* @var $controller ControllerAbstract */
        /* @var $response RedirectResponse */

        $response = $controller->redirect($route);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($url, $response->location);
        $this->assertSame(302, $response->status->code);
    }

    /**
     * @dataProvider provide_test_forward_to_invalid
     *
     *
     * @param mixed $invalid
     */
    public function test_forward_to_invalid($invalid)
    {
        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var $controller ControllerAbstract */

        $this->expectException(\InvalidArgumentException::class);
        $controller->forward_to($invalid);
    }

    public function provide_test_forward_to_invalid()
    {
        return [

            [ uniqid() ],
            [ (object) [ uniqid() => uniqid() ] ],
            [ [ uniqid() => uniqid() ] ]

        ];
    }

    public function test_forward_to_route()
    {
        $original_request = Request::from('/articles/123/edit');
        $response = new Response();

        $controller = $this
            ->getMockBuilder(ControllerAbstract::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        /* @var $response RedirectResponse */
        /* @var $controller ControllerAbstract */

        $route = new Route('/articles/<nid:\d+>/edit', [

            'controller' => function (Request $request) use ($original_request, $response) {
                $this->assertNotSame($original_request, $request);
                $this->assertEquals(123, $request['nid']);

                return $response;
            }

        ]);

        $controller->respond($original_request); // only to set private `request` property

        $this->assertSame($response, $controller->forward_to($route));
    }
}
