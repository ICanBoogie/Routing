<?php

namespace ICanBoogie\Routing\IntoResponse;

use Closure;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Routing\IntoResponse;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;

use function array_is_list;
use function array_map;
use function array_walk_recursive;
use function get_debug_type;
use function implode;
use function is_array;
use function is_object;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Turns data into a JSON response.
 *
 * The data is encoded by a single encoder. A list is encoded item by item, so that the encoder only
 * ever deals with one value, e.g. one object for a serializer. Keyed arrays are encoded as a whole.
 *
 * The default encoder handles arrays and scalars as they are, and refuses any object that is not
 * {@see JsonSerializable}, wherever it is found in the data, so that nothing is exposed by accident,
 * e.g. the public properties of an entity. Provide `$encode` to use a serializer, in which case you
 * decide what is encoded.
 */
final readonly class Json implements IntoResponse
{
    /**
     * @param mixed $value
     *     The value to encode: a value, a keyed array, or a list of values.
     * @param int $status
     *     The status code of the response.
     * @param (Closure(mixed): string)|null $encode
     *     Encodes a value to a JSON string, it is called for each item when `$data` is a list.
     *     Defaults to `json_encode()` with `JSON_THROW_ON_ERROR`.
     * @param (Closure(Response): void)|null $tweak
     *     Called with the response just before it is returned, after the status and content type
     *     are set, e.g. `fn(Response $r) => $r->headers->cache_control->max_age = 60`.
     */
    public function __construct(
        private mixed $value,
        private int $status = Status::OK,
        private ?Closure $encode = null,
        private ?Closure $tweak = null,
    ) {
    }

    /**
     * @throws InvalidArgumentException if the default encoder meets an object that is not {@see JsonSerializable}.
     * @throws JsonException if the default encoder fails.
     */
    public function into_response(Request $request): Response
    {
        $value = $this->value;
        $encode = $this->encode ?? self::encode_default(...);

        $body = is_array($value) && array_is_list($value)
            ? '[' . implode(',', array_map($encode, $value)) . ']'
            : $encode($value);

        $response = new Response($body, $this->status, [ 'Content-Type' => 'application/json' ]);

        if ($this->tweak) {
            ($this->tweak)($response);
        }

        return $response;
    }

    private static function encode_default(mixed $value): string
    {
        if (is_array($value)) {
            array_walk_recursive($value, self::assert_encodable(...));
        } else {
            self::assert_encodable($value);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function assert_encodable(mixed $value): void
    {
        if (is_object($value) && !$value instanceof JsonSerializable) {
            throw new InvalidArgumentException(
                sprintf(
                    "Unable to encode %s, implement JsonSerializable or provide an encoder.",
                    get_debug_type($value)
                )
            );
        }
    }
}
