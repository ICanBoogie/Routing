<?php

namespace Test\ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Attribute\Name;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Name::class)]
final class NameTest extends TestCase
{
    public function test_value(): void
    {
        $this->assertSame('contact', new Name('contact')->value);
    }

    #[DataProvider('provide_not_valid')]
    public function test_not_valid(string $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Name($value);
    }

    /**
     * @return array<string, array{ string, string }>
     */
    public static function provide_not_valid(): array
    {
        return [

            'blank' => [ '', "The name cannot be blank." ],
            'separator' => [ 'a:b', "The name `a:b` cannot contain `:`." ],

        ];
    }

    public function test_is_an_attribute_of_classes(): void
    {
        $attribute = new ReflectionClass(Name::class)->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertSame(Attribute::TARGET_CLASS, $attribute->flags);
    }
}
