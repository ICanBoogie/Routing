<?php

namespace Test\ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\MethodResponder;
use ICanBoogie\Routing\RequestResponderProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\ConfigBuilder\PhotosController;
use Test\ICanBoogie\Routing\ConfigBuilder\SearchController;

#[CoversClass(FromConfig::class)]
final class FromConfigTest extends TestCase
{
    private int $created = 0;

    public function test_unknown_action(): void
    {
        $this->assertNull($this->provider()->responder_for_action('photos:unknown'));
    }

    public function test_known_action(): void
    {
        $provider = $this->provider();
        $responder = $provider->responder_for_action('photos:show');

        $this->assertInstanceOf(MethodResponder::class, $responder);
        $this->assertSame($responder, $provider->responder_for_action('photos:show'));
        $this->assertSame($responder, $provider->responder_for_action('photos:list'));
        $this->assertSame(0, $this->created);
    }

    public function test_end_to_end(): void
    {
        $provider = $this->request_provider();
        $request = Request::from([ 'uri' => '/photos/123', 'method' => RequestMethod::METHOD_GET ]);

        $response = $provider->responder_for_request($request)->respond($request);

        $this->assertSame(200, $response->status->code);
        $this->assertSame(1, $this->created);
    }

    public function test_not_valid_path_parameter_is_not_found(): void
    {
        $provider = $this->request_provider();
        $request = Request::from([ 'uri' => '/photos/abc', 'method' => RequestMethod::METHOD_GET ]);

        $this->expectException(PathParameterNotValid::class);

        $provider->responder_for_request($request)->respond($request);
    }

    public function test_query_and_part_parameters(): void
    {
        $provider = $this->request_provider(SearchController::class);

        $request = Request::from([
            'uri' => '/search/books',
            'method' => RequestMethod::METHOD_GET,
            'query_params' => [ 'q' => 'php' ],
        ]);
        $this->assertSame('books:php:1', $provider->responder_for_request($request)->respond($request)->body);

        $request = Request::from([
            'uri' => '/search/books',
            'method' => RequestMethod::METHOD_GET,
            'query_params' => [ 'q' => 'php', 'page' => '3' ],
        ]);
        $this->assertSame('books:php:3', $provider->responder_for_request($request)->respond($request)->body);

        $request = Request::from([
            'uri' => '/search/books',
            'method' => RequestMethod::METHOD_POST,
            'request_params' => [ 'title' => 'Fav', 'public' => true ],
        ]);
        $this->assertSame("books:Fav:true", $provider->responder_for_request($request)->respond($request)->body);
    }

    public function test_missing_query_parameter_is_a_bad_request(): void
    {
        $provider = $this->request_provider(SearchController::class);
        $request = Request::from([ 'uri' => '/search/books', 'method' => RequestMethod::METHOD_GET ]);

        try {
            $provider->responder_for_request($request)->respond($request);
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertTrue($e->missing);
        }
    }

    public function test_no_route_no_responder(): void
    {
        $provider = $this->request_provider();
        $request = Request::from([ 'uri' => '/nothing', 'method' => RequestMethod::METHOD_GET ]);

        $this->assertNull($provider->responder_for_request($request));
    }

    private function provider(): FromConfig
    {
        return new FromConfig(
            new ConfigBuilder()->add_controller(PhotosController::class)->build(),
            function (string $class): object {
                $this->created++;

                return new $class();
            }
        );
    }

    /**
     * @param class-string $controller
     */
    private function request_provider(string $controller = PhotosController::class): RequestResponderProvider
    {
        $config = new ConfigBuilder()->add_controller($controller)->build();

        return new RequestResponderProvider(
            $config->routes,
            new FromConfig($config, function (string $class): object {
                $this->created++;

                return new $class();
            })
        );
    }
}
