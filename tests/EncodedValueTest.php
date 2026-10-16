<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\Decoder\Slug;

#[CoversClass(EncodedValue::class)]
final class EncodedValueTest extends TestCase
{
    public function test_exposes_value_source_and_name(): void
    {
        $encoded = new EncodedValue('my-slug', 'path', Slug::class, 'slug');

        $this->assertSame('my-slug', $encoded->value);
        $this->assertSame('path', $encoded->source);
        $this->assertSame(Slug::class, $encoded->into_class);
        $this->assertSame('slug', $encoded->name);
    }

    public function test_name_defaults_to_null(): void
    {
        $this->assertNull(new EncodedValue('', 'body', Slug::class)->name);
    }

    public function test_decoder_implementations_can_narrow_the_return_type(): void
    {
        $decoder = new class () implements Decoder {
            public static function decode(EncodedValue $value): \stdClass
            {
                return (object) [ 'slug' => $value->value ];
            }
        };

        $this->assertSame('a', $decoder::decode(new EncodedValue('a', 'path', \stdClass::class, 'slug'))->slug);
    }
}
