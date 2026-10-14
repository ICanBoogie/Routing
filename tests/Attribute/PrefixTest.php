<?php

namespace Test\ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Attribute\Prefix;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Prefix::class)]
final class PrefixTest extends TestCase
{
    public function test_both(): void
    {
        $prefix = new Prefix(pattern: '/cart', action: 'cart');

        $this->assertSame('/cart', $prefix->pattern);
        $this->assertSame('cart', $prefix->action);
    }

    public function test_pattern_only(): void
    {
        $prefix = new Prefix(pattern: '/cart');

        $this->assertSame('/cart', $prefix->pattern);
        $this->assertNull($prefix->action);
    }

    public function test_action_only(): void
    {
        $prefix = new Prefix(action: 'cart');

        $this->assertNull($prefix->pattern);
        $this->assertSame('cart', $prefix->action);
    }

    public function test_at_least_one_is_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("At least one of `pattern` or `action` is required.");

        new Prefix();
    }

    #[DataProvider('provide_blank')]
    public function test_blank_is_not_allowed(?string $pattern, ?string $action): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("The `pattern` and the `action` cannot be blank.");

        new Prefix($pattern, $action);
    }

    /**
     * @return array<string, array{ string|null, string|null }>
     */
    public static function provide_blank(): array
    {
        return [

            'blank pattern' => [ '', null ],
            'blank action' => [ null, '' ],
            'blank pattern, action' => [ '', 'cart' ],
            'pattern, blank action' => [ '/cart', '' ],

        ];
    }

    public function test_is_an_attribute_of_classes(): void
    {
        $attribute = new ReflectionClass(Prefix::class)->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertSame(Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function test_can_be_extended(): void
    {
        $extension = new readonly class () extends Prefix {
            public function __construct()
            {
                parent::__construct(pattern: '/cart', action: 'cart');
            }
        };

        $this->assertSame('/cart', $extension->pattern);
        $this->assertSame('cart', $extension->action);
    }

    public function test_read_from_a_class(): void
    {
        $controller = new #[Prefix(pattern: '/cart', action: 'cart')] class {
        };

        $attributes = new ReflectionClass($controller)->getAttributes(Prefix::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('/cart', $attributes[0]->newInstance()->pattern);
    }
}
