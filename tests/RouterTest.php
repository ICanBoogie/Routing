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

use ICanBoogie\HTTP\NotFound;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ActionResponderProvider;
use ICanBoogie\Routing\RequestResponderProvider;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider\ByUri;
use ICanBoogie\Routing\RouteProvider\Mutable;
use ICanBoogie\Routing\Router;
use PHPUnit\Framework\TestCase;
use Throwable;

final class RouterTest extends TestCase
{
    /**
     * @dataProvider provide_method
     * @throws Throwable
     */
    public function test_method(string $method, RequestMethod $http_method): void
    {
        $response = new Response();
        $pattern = '/articles/<\d+>';
        $closure = fn(): Response => $response;

        $routes = new Mutable();
        $responders = new ActionResponderProvider\Mutable();

        $router = new Router($routes, $responders);
        $router->$method($pattern, $closure);
        $route = $routes->route_for_predicate(
            new ByUri(
                "/articles/123",
                $http_method
            )
        );

        assert($route instanceof Route);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertInstanceOf(Responder::class, $responder = $responders->responder_for_action($route->action));
        $this->assertSame($response, $responder->respond(Request::from()));
    }

    /**
     * @return mixed[]
     */
    public function provide_method(): array
    {
        return [

            [ 'any', RequestMethod::METHOD_ANY ],
            [ 'connect', RequestMethod::METHOD_CONNECT ],
            [ 'delete', RequestMethod::METHOD_DELETE ],
            [ 'get', RequestMethod::METHOD_GET ],
            [ 'head', RequestMethod::METHOD_HEAD ],
            [ 'options', RequestMethod::METHOD_OPTIONS ],
            [ 'patch', RequestMethod::METHOD_PATCH ],
            [ 'post', RequestMethod::METHOD_POST ],
            [ 'put', RequestMethod::METHOD_PUT ],
            [ 'trace', RequestMethod::METHOD_TRACE ],

        ];
    }

    /**
     * @throws NotFound
     */
    public function test_route(): void
    {
        $routes = new Mutable();
        $responders = new ActionResponderProvider\Mutable();
        $response = new Response();

        (new Router($routes, $responders))
            ->get(
                '/articles/<id:\d+>',
                function (Request $request) use ($response): Response {
                    $id = $request->path_params['id'];

                    $this->assertSame('123', $id);

                    return $response;
                }
            )
            ->get(
                '/it-s-a-trap',
                function (): Response {
                    $this->fail("should not be called");
                }
            );

        $request = Request::from([ Request::OPTION_PATH => '/articles/123' ]);
        $responder = new Responder\DelegateToProvider(new RequestResponderProvider($routes, $responders));
        $actual = $responder->respond($request);

        $this->assertSame($response, $actual);
    }
}
