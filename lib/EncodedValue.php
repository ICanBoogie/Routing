<?php

namespace ICanBoogie\Routing;

/**
 * A value of the request, as provided by the client, for a {@see Decoder} to turn into an object.
 *
 * A decoder is never called for a missing value, `$value` is never `null`.
 */
final readonly class EncodedValue
{
    /**
     * @param mixed $value
     *     The value, which depends on the source:
     *
     *     - `path`: a `string`.
     *     - `query`, `part`: the value found under `$name`, a `string`, a number or a boolean (JSON),
     *       or an `array`.
     *     - `body`: a `SplFileObject` opened on the raw body.
     * @param 'path'|'query'|'part'|'body' $source
     *     Where the value comes from.
     * @param non-empty-string|null $name
     *     Name of the value in its source, `null` for the body.
     */
    public function __construct(
        public mixed $value,
        public string $source,
        public ?string $name = null,
    ) {
    }
}
