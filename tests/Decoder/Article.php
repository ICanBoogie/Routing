<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;

/**
 * A type that decodes itself.
 */
final readonly class Article implements Decoder
{
    public static function decode(EncodedValue $value): static
    {
        return new self($value->source . ':' . $value->name . ':' . json_encode($value->value));
    }

    public function __construct(public string $description)
    {
    }
}
