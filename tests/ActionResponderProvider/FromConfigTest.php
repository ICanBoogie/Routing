<?php

namespace Test\ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\MethodResponder;
use ICanBoogie\Routing\RequestResponderProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\ConfigBuilder\PhotosController;

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

    private function request_provider(): RequestResponderProvider
    {
        $config = new ConfigBuilder()->add_controller(PhotosController::class)->build();

        return new RequestResponderProvider(
            $config->routes,
            new FromConfig($config, function (string $class): object {
                $this->created++;

                return new $class();
            })
        );
    }
}
