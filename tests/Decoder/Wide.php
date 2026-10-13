<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use stdClass;

/**
 * A decoder that doesn't narrow its return type.
 */
final class Wide implements Decoder
{
    public static function decode(EncodedValue $value): object
    {
        return new stdClass();
    }
}
