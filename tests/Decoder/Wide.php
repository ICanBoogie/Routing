<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use stdClass;

/**
 * A decoder that doesn't narrow its return type, it makes whatever class it is asked for.
 */
final class Wide implements Decoder
{
    public static function decode(EncodedValue $value): object
    {
        return new ($value->into_class === 'object' ? stdClass::class : $value->into_class)($value->value);
    }
}
