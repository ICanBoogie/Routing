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

use ICanBoogie\HTTP\Exception\NoResponder;
use ICanBoogie\HTTP\MethodNotAllowed;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\ResponderProvider;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ActionResponderProvider;
use ICanBoogie\Routing\RequestResponderProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByUri;
use olvlvl\Given\GivenTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Throwable;

use function uniqid;

final class RequestResponderProviderTest extends TestCase
{
    use GivenTrait;

    /**
     * @var MockObject&RouteProvider
     */
    private RouteProvider $routes;

    /**
     * @var MockObject&ActionResponderProvider
     */
    private ActionResponderProvider $responders;
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = $this->createMock(RouteProvider::class);
        $this->responders = $this->createMock(ActionResponderProvider::class);
        $this->request = Request::from([
            RequestOptions::OPTION_URI => '/' . uniqid(),
            RequestOptions::OPTION_METHOD => RequestMethod::METHOD_POST,
        ]);
    }

    public function test_unsupported_method(): void
    {
        $request = $this->request;

        $this->routes
            ->method('route_for_predicate')
            ->will(
                $this
                    ->given(new ByUri($request->uri, $request->method))->return(null)
                    ->given(new ByUri($request->uri))->return(new Route('/', 'action'))
            );

        $stu = $this->makeSTU();

        $this->expectException(MethodNotAllowed::class);
        $this->expectExceptionMessage("Method not allowed: POST.");

        $stu->responder_for_request($request);
    }

    public function test_route_not_found(): void
    {
        $request = $this->request;

        $this->routes
            ->method('route_for_predicate')
            ->will(
                $this
                    ->given(new ByUri($request->uri, $request->method))->return(null)
                    ->given(new ByUri($request->uri))->return(null)
            );

        $stu = $this->makeSTU();

        $this->assertNull($stu->responder_for_request($this->request));
    }

    public function test_no_responder_for_route(): void
    {
        $request = $this->request;

        $this->routes
            ->method('route_for_predicate')
            ->with(new ByUri($request->uri, $request->method))
            ->willReturn(new Route('/', $action = 'articles:create'));

        $this->responders
            ->method('responder_for_action')
            ->with($action)
            ->willReturn(null);

        $this->expectException(NoResponder::class);
        $this->expectExceptionMessage("No responder for action: articles:create.");

        $this->makeSTU()->responder_for_request($this->request);
    }

    /**
     * @throws Throwable
     */
    public function test_returns_responder(): void
    {
        $request = $this->request;
        $response = new Response();
        $route = new Route('/', $action = 'articles:create');
        $path_params = [ 'id' => uniqid() ];

        $responder = $this->createMock(Responder::class);
        $responder
            ->method('respond')
            ->willReturnCallback(function (Request $r) use ($request, $route, $path_params, $response): Response {
                $this->assertSame($request, $r);
                $this->assertSame($route, $r->context->get(Route::class));
                $this->assertSame($path_params, $r->path_params);
                $this->assertSame($path_params, $r->params);

                return $response;
            });

        $this->routes
            ->method('route_for_predicate')
            ->willReturnCallback(function (ByUri $predicate) use ($path_params, $route) {
                $predicate->path_params = $path_params;

                return $route;
            });

        $this->responders
            ->method('responder_for_action')
            ->with($action)
            ->willReturn($responder);

        $r = $this->makeSTU()->responder_for_request($this->request);

        $this->assertSame($response, $r?->respond($request));
    }

    private function makeSTU(): ResponderProvider
    {
        return new RequestResponderProvider(
            $this->routes,
            $this->responders,
        );
    }
}
