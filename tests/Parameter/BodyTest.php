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
    public function test_resolve_is_the_body_stream_of_the_request(): void
    {
        $stream = new SplFileObject('php://memory', 'w+');
        $stream->fwrite('<xml/>');
        $stream->rewind();

        $request = Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_BODY_STREAM => $stream,
        ]);

        $this->assertSame($stream, new Body()->resolve($request));
        $this->assertSame('<xml/>', $stream->fgets());
    }

    public function test_a_request_without_a_stream_has_an_empty_body(): void
    {
        $file = new Body()->resolve(Request::from([ RequestOptions::OPTION_URI => '/' ]));

        $this->assertInstanceOf(SplFileObject::class, $file);
        $this->assertTrue($file->eof() || $file->fgets() === '');
    }

    public function test_export(): void
    {
        $this->assertEquals(new Body(), SetStateHelper::export_import(new Body()));
    }
}
