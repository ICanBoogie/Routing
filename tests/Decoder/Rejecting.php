<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use InvalidArgumentException;
use LogicException;
use ValueError;

/**
 * Throws what its value says.
 */
final class Rejecting implements Decoder
{
    public static function decode(EncodedValue $value): Slug
    {
        throw match ($value->value) {
            'invalid-argument' => new InvalidArgumentException("Nope"),
            'value-error' => new ValueError("Nope"),
            default => new LogicException("A bug"),
        };
    }
}
