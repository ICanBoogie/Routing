<?php

namespace Test\ICanBoogie\Routing\Decoder;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use ICanBoogie\Routing\Exception\NotDecodable;

use function is_string;
use function preg_match;

final class SlugDecoder implements Decoder
{
    public static function decode(EncodedValue $value): Slug
    {
        return is_string($value->value) && preg_match('/^[a-z0-9-]+$/', $value->value)
            ? new Slug($value->value)
            : throw new NotDecodable("Not a slug");
    }
}
