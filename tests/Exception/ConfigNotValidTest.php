<?php

namespace Test\ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(ConfigNotValid::class)]
final class ConfigNotValidTest extends TestCase
{
    public function test_for_method(): void
    {
        $exception = ConfigNotValid::for_method(new ReflectionMethod($this, 'action'), "has no route.");

        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame("`" . self::class . "::action()` has no route.", $exception->getMessage());
    }

    public function test_for_parameter(): void
    {
        $method = new ReflectionMethod($this, 'action');
        $exception = ConfigNotValid::for_parameter($method, $method->getParameters()[0], "no `:slug`.");

        $this->assertSame(
            "`" . self::class . "::action()` parameter `\$slug`: no `:slug`.",
            $exception->getMessage()
        );
    }

    public function action(string $slug): void
    {
    }
}
