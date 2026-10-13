<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(CurrentRequest::class)]
final class CurrentRequestTest extends TestCase
{
    public function test_resolve(): void
    {
        $request = Request::from([ RequestOptions::OPTION_URI => '/' ]);

        $this->assertSame($request, new CurrentRequest()->resolve($request));
    }

    public function test_export(): void
    {
        $parameter = new CurrentRequest();

        $this->assertEquals($parameter, SetStateHelper::export_import($parameter));
    }
}
