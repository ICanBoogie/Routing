<?php

namespace Test\ICanBoogie\Routing\Exception;

use ICanBoogie\Routing\Exception;
use ICanBoogie\Routing\Exception\NotDecodable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

#[CoversClass(NotDecodable::class)]
final class NotDecodableTest extends TestCase
{
    public function test_is_a_routing_exception(): void
    {
        $previous = new RuntimeException();
        $exception = new NotDecodable("Not a slug: 'A B'", previous: $previous);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(UnexpectedValueException::class, $exception);
        $this->assertSame("Not a slug: 'A B'", $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
