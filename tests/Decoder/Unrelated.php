<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use stdClass;

final class Unrelated implements Decoder
{
    public static function decode(EncodedValue $value): stdClass
    {
        return new stdClass();
    }
}
