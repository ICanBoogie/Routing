<?php

namespace Test\ICanBoogie\Routing\IntoResponse;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\IntoResponse\Json;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    private function request(): Request
    {
        return Request::from('/');
    }

    public function test_encodes_a_keyed_array(): void
    {
        $response = new Json([ 'id' => 1, 'name' => 'x' ])->into_response($this->request());

        $this->assertSame('{"id":1,"name":"x"}', $response->body);
        $this->assertSame('application/json', (string) $response->headers->content_type);
        $this->assertSame(200, $response->status->code);
    }

    public function test_encodes_a_list_and_scalars(): void
    {
        $this->assertSame('[1,2]', new Json([ 1, 2 ])->into_response($this->request())->body);
        $this->assertSame('null', new Json(null)->into_response($this->request())->body);
    }

    public function test_encodes_a_json_serializable(): void
    {
        $value = new class implements JsonSerializable {
            public string $secret = 'nope';

            public function jsonSerialize(): array
            {
                return [ 'a' => 1 ];
            }
        };

        $this->assertSame('{"a":1}', new Json($value)->into_response($this->request())->body);
    }

    public function test_rejects_a_plain_object(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Json(new \stdClass())->into_response($this->request());
    }

    public function test_throws_on_encoding_error(): void
    {
        $this->expectException(JsonException::class);

        new Json(NAN)->into_response($this->request());
    }

    public function test_uses_status(): void
    {
        $response = new Json([], status: 201)->into_response($this->request());

        $this->assertSame(201, $response->status->code);
    }

    public function test_custom_encoder_accepts_any_object(): void
    {
        $object = new \stdClass();
        $object->a = 1;

        $json = new Json($object, encode: fn(mixed $o): string => '{"custom":true}');

        $this->assertSame('{"custom":true}', $json->into_response($this->request())->body);
    }

    public function test_tweak_has_the_last_word(): void
    {
        $response = new Json(
            [],
            tweak: function (Response $r): void {
                $r->headers->cache_control->max_age = 60;
                $r->headers['Content-Type'] = 'application/vnd.api+json';
            }
        )->into_response($this->request());

        $this->assertSame(60, $response->headers->cache_control->max_age);
        $this->assertSame('application/vnd.api+json', (string) $response->headers->content_type);
    }

    public function test_rejects_a_plain_object_nested_in_an_array(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Json([ 'item' => new \stdClass() ])->into_response($this->request());
    }

    public function test_rejects_a_plain_object_in_a_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Json([ new \stdClass() ])->into_response($this->request());
    }

    public function test_encodes_a_list_of_json_serializable(): void
    {
        $item = static fn(int $id): JsonSerializable => new class($id) implements JsonSerializable {
            public function __construct(private int $id)
            {
            }

            public function jsonSerialize(): array
            {
                return [ 'id' => $this->id ];
            }
        };

        $this->assertSame(
            '[{"id":1},{"id":2}]',
            new Json([ $item(1), $item(2) ])->into_response($this->request())->body
        );
    }

    public function test_custom_encoder_is_called_for_each_item_of_a_list(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $seen = [];

        $json = new Json([ $a, $b ], encode: function (mixed $o) use (&$seen): string {
            $seen[] = $o;

            return '"x"';
        });

        $this->assertSame('["x","x"]', $json->into_response($this->request())->body);
        $this->assertSame([ $a, $b ], $seen);
    }

    public function test_custom_encoder_receives_a_keyed_array_whole(): void
    {
        $json = new Json([ 'a' => 1 ], encode: fn(mixed $v): string => (string) count($v));

        $this->assertSame('1', $json->into_response($this->request())->body);
    }

    public function test_empty_list(): void
    {
        $this->assertSame('[]', new Json([])->into_response($this->request())->body);
    }
}
