<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\IntoResponse;
use ICanBoogie\Routing\MethodResponder;
use ICanBoogie\Routing\ParameterAbstract;
use ICanBoogie\Routing\Parameter\Casting;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use TypeError;

#[CoversClass(MethodResponder::class)]
#[UsesClass(ActionDefinition::class)]
#[UsesClass(ParameterAbstract::class)]
#[UsesClass(Casting::class)]
#[UsesClass(CurrentRequest::class)]
#[UsesClass(MatchedRoute::class)]
#[UsesClass(Path::class)]
#[UsesClass(Pattern::class)]
#[UsesClass(Route::class)]
final class MethodResponderTest extends TestCase
{
    public function test_is_a_responder(): void
    {
        $this->assertInstanceOf(Responder::class, new MethodResponder(fn() => new \stdClass(), []));
    }

    public function test_calls_the_method_of_the_action(): void
    {
        $controller = new class {
            public function list(): Response
            {
                return new Response('list');
            }

            public function show(): Response
            {
                return new Response('show');
            }
        };

        $responder = new MethodResponder(
            fn() => $controller,
            [
                'articles:list' => new ActionDefinition('list'),
                'articles:show' => new ActionDefinition('show'),
            ]
        );

        $this->assertSame('list', $responder->respond($this->request('articles:list'))->body);
        $this->assertSame('show', $responder->respond($this->request('articles:show'))->body);
    }

    public function test_arguments_come_from_the_plan_in_order(): void
    {
        $controller = new class {
            public function show(Request $request, int $year, Route $route, string $slug): Response
            {
                return new Response("$request->uri|$year|$slug|$route->action");
            }
        };

        $responder = new MethodResponder(
            fn() => $controller,
            [
                'articles:show' => new ActionDefinition('show', [
                    new CurrentRequest(),
                    new Path('year', 'int'),
                    new MatchedRoute(),
                    new Path('slug', 'string'),
                ])
            ]
        );

        $request = $this->request('articles:show', '/2026/hello', [ 'slug' => 'hello', 'year' => '2026' ]);

        $this->assertSame('/2026/hello|2026|hello|articles:show', $responder->respond($request)->body);
    }

    public function test_returns_the_response_as_is(): void
    {
        $response = new Response('hello');
        $controller = new class ($response) {
            public function __construct(private Response $response)
            {
            }

            public function show(): Response
            {
                return $this->response;
            }
        };

        $responder = new MethodResponder(fn() => $controller, [ 'a:b' => new ActionDefinition('show') ]);

        $this->assertSame($response, $responder->respond($this->request('a:b')));
    }

    public function test_converts_into_response(): void
    {
        $request = $this->request('a:b');
        $response = new Response('converted');
        $wrapper = new class ($response) implements IntoResponse {
            public ?Request $received = null;

            public function __construct(private readonly Response $response)
            {
            }

            public function into_response(Request $request): Response
            {
                $this->received = $request;

                return $this->response;
            }
        };
        $controller = new class ($wrapper) {
            public function __construct(private IntoResponse $wrapper)
            {
            }

            public function show(): IntoResponse
            {
                return $this->wrapper;
            }
        };

        $responder = new MethodResponder(fn() => $controller, [ 'a:b' => new ActionDefinition('show') ]);

        $this->assertSame($response, $responder->respond($request));
        $this->assertSame($request, $wrapper->received);
    }

    #[DataProvider('provide_invalid_result')]
    public function test_invalid_result(mixed $result, string $type): void
    {
        $controller = new class ($result) {
            public function __construct(private mixed $result)
            {
            }

            public function show(): mixed
            {
                return $this->result;
            }
        };

        $responder = new MethodResponder(fn() => $controller, [ 'a:b' => new ActionDefinition('show') ]);

        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches(
            '/::show\(\) must return ICanBoogie\\\\HTTP\\\\Response or ICanBoogie\\\\Routing\\\\IntoResponse, ' .
            $type . ' returned\./'
        );

        $responder->respond($this->request('a:b'));
    }

    /**
     * @return array<string, array{ mixed, string }>
     */
    public static function provide_invalid_result(): array
    {
        return [

            'null' => [ null, 'null' ],
            'string' => [ 'hello', 'string' ],
            'array' => [ [ 'a' ], 'array' ],
            'object' => [ new \stdClass(), 'stdClass' ],

        ];
    }

    public function test_action_not_handled(): void
    {
        $responder = new MethodResponder(fn() => new \stdClass(), [ 'a:b' => new ActionDefinition('show') ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("The responder doesn't handle the action 'a:c'.");

        $responder->respond($this->request('a:c'));
    }

    public function test_controller_is_obtained_for_each_request(): void
    {
        $count = 0;
        $responder = new MethodResponder(
            function () use (&$count) {
                $count++;

                return new class {
                    public function show(): Response
                    {
                        return new Response();
                    }
                };
            },
            [ 'a:b' => new ActionDefinition('show') ]
        );

        $responder->respond($this->request('a:b'));
        $responder->respond($this->request('a:b'));

        $this->assertSame(2, $count);
    }

    public function test_controller_is_not_obtained_when_the_action_is_not_handled(): void
    {
        $responder = new MethodResponder(
            fn() => $this->fail("The controller shouldn't be obtained"),
            []
        );

        $this->expectException(LogicException::class);

        $responder->respond($this->request('a:b'));
    }

    /**
     * @param array<string, string> $path_params
     */
    private function request(string $action, string $uri = '/', array $path_params = []): Request
    {
        $request = Request::from([
            RequestOptions::OPTION_URI => $uri,
            RequestOptions::OPTION_PATH_PARAMS => $path_params,
        ]);
        $request->context->add(new Route('/', $action));

        return $request;
    }
}
