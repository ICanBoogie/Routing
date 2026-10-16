<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\Exception\NotDecodable;

/**
 * Turns an encoded value of the request into an object.
 *
 * A decoder is bound to a parameter of an action with the `using` argument of `#[Path]`, `#[Query]`,
 * `#[Part]`, or `#[Body]`, e.g. `#[Path(using: SlugDecoder::class)] Slug $slug`. A class that
 * decodes itself, and is the type of the parameter, doesn't need `using`.
 *
 * Decoders are static: pure functions of their input, without state or services, so that the plan
 * of an action remains plain data. The return type is `object`, implementations narrow it:
 * `decode(EncodedValue $value): Slug`. A decoder that serves many types keeps `object` and reads
 * the class to produce from {@see EncodedValue::$into_class}.
 *
 * The design is explained in docs/adr/0002-decoders.md.
 */
interface Decoder
{
    /**
     * @throws NotDecodable if the value is not valid. `InvalidArgumentException` and `ValueError`
     *     are treated the same way.
     */
    public static function decode(EncodedValue $value): object;
}
