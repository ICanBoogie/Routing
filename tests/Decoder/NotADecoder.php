<?php

namespace Test\ICanBoogie\Routing\Decoder;

final class NotADecoder
{
    public static function decode(): Slug
    {
        return new Slug('');
    }
}
