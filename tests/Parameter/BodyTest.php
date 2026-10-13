<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Parameter\Body;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(Body::class)]
final class BodyTest extends TestCase
{
    public function test_resolve_is_a_file_object_on_the_input(): void
    {
        $file = new Body()->resolve(Request::from([ RequestOptions::OPTION_URI => '/' ]));

        $this->assertSame('php://input', $file->getPathname());
    }

    public function test_export(): void
    {
        $this->assertEquals(new Body(), SetStateHelper::export_import(new Body()));
    }
}
