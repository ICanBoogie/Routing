<?php

namespace Test\ICanBoogie\Routing\Middleware;

use ICanBoogie\HTTP\ClientError;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\MiddlewareCollection;
use ICanBoogie\Routing\RequestResponderProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use Test\ICanBoogie\Routing\ConfigBuilder\SearchController;

/**
 * Verifies what docs/StatelessControllers.md says about `#[Part]` and a JSON body: `Request` doesn't
 * decode it, so it must be decoded into `request_params` first.
 */
final class DecodeJsonBodyTest extends TestCase
{
    private RequestResponderProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new ConfigBuilder()->add_controller(SearchController::class)->build();
        $this->provider = new RequestResponderProvider(
            $config->routes,
            new FromConfig($config, fn(string $class): object => new $class())
        );
    }

    public function test_part_finds_nothing_in_a_json_body_that_is_not_decoded(): void
    {
        $request = $this->json_request('{"title":"Fav","public":true}');

        try {
            $this->responder($request)->respond($request);
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertTrue($e->missing);
        }
    }

    public function test_part_reads_a_decoded_json_body(): void
    {
        $this->assertSame('books:Fav:true', $this->respond('{"title":"Fav","public":true}'));
    }

    public function test_content_type_may_have_a_charset(): void
    {
        $this->assertSame(
            'books:Fav:true',
            $this->respond('{"title":"Fav","public":true}', 'application/json; charset=utf-8')
        );
    }

    public function test_other_content_types_are_left_alone(): void
    {
        $request = $this->json_request('{"title":"Fav"}', 'text/plain');
        $request->request_params = [ 'title' => 'Form' ];

        $this->assertSame('books:Form:NULL', $this->chain($request));
    }

    #[DataProvider('provide_not_decodable')]
    public function test_invalid_body_is_a_client_error(string $json): void
    {
        $this->expectException(ClientError::class);

        $this->respond($json);
    }

    /**
     * @return array<string, array{ string }>
     */
    public static function provide_not_decodable(): array
    {
        return [
            'list' => [ '[1,2]' ],
            'empty body' => [ '' ],
            'malformed JSON' => [ '{bad' ],
            'not an object' => [ '"string"' ],
        ];
    }

    public function test_missing_property_is_a_bad_request(): void
    {
        $this->expectException(ParameterNotValid::class);

        $this->respond('{"public":true}');
    }

    public function test_wrong_type_is_a_bad_request(): void
    {
        $this->expectException(ParameterNotValid::class);

        $this->respond('{"title":5}');
    }

    private function respond(string $json, string $content_type = 'application/json'): string
    {
        return $this->chain($this->json_request($json, $content_type));
    }

    private function chain(Request $request): string
    {
        $body = new MiddlewareCollection([ new DecodeJsonBody() ])
            ->chain($this->responder($request))
            ->respond($request)
            ->body;

        $this->assertIsString($body);

        return $body;
    }

    private function responder(Request $request): Responder
    {
        $responder = $this->provider->responder_for_request($request);

        $this->assertNotNull($responder);

        return $responder;
    }

    private function json_request(string $json, string $content_type = 'application/json'): Request
    {
        $stream = new SplFileObject('php://memory', 'w+');
        $stream->fwrite($json);
        $stream->rewind();

        return Request::from([
            'uri' => '/search/books',
            'method' => RequestMethod::METHOD_POST,
            'headers' => [ 'Content-Type' => $content_type ],
            Request::OPTION_BODY_STREAM => $stream,
        ]);
    }
}
