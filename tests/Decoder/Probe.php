<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;

/**
 * Tells what it received.
 */
final class Probe implements Decoder
{
    public static function decode(EncodedValue $value): Slug
    {
        return new Slug(implode('|', [
            $value->source,
            var_export($value->name, true),
            get_debug_type($value->value),
            $value->value->getPathname(),
        ]));
    }
}
