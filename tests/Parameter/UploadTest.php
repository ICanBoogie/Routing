<?php

namespace Test\ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\File;
use ICanBoogie\HTTP\FileList;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Parameter\Upload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;

#[CoversClass(Upload::class)]
#[CoversClass(ParameterNotValid::class)]
final class UploadTest extends TestCase
{
    public function test_resolve_provides_the_file(): void
    {
        $file = new File(pathname: __FILE__);
        $actual = new Upload('image')->resolve($this->request([ 'image' => $file ]));

        $this->assertSame($file, $actual);
    }

    public function test_resolve_uses_the_name(): void
    {
        $file = new File(pathname: __FILE__);
        $request = $this->request([ 'other' => new File(pathname: __DIR__ . '/../../composer.json'), 'photo' => $file ]);

        $actual = new Upload('photo')->resolve($request);

        $this->assertSame($file, $actual);
    }

    public function test_missing_is_a_bad_request(): void
    {
        try {
            new Upload('image')->resolve($this->request([]));
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('upload', $e->source);
            $this->assertSame('image', $e->name);
            $this->assertSame(File::class, $e->type);
            $this->assertTrue($e->missing);
            $this->assertSame("The upload parameter 'image' is required.", $e->getMessage());
        }
    }

    public function test_missing_nullable_is_null(): void
    {
        $actual = new Upload('image', true)->resolve($this->request([]));

        $this->assertNull($actual);
    }

    public function test_no_file_is_missing(): void
    {
        $file = new File(error: UPLOAD_ERR_NO_FILE);

        $actual = new Upload('image', true)->resolve($this->request([ 'image' => $file ]));

        $this->assertNull($actual);

        try {
            new Upload('image')->resolve($this->request([ 'image' => $file ]));
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertTrue($e->missing);
        }
    }

    public function test_failed_upload_is_not_valid_even_if_nullable(): void
    {
        $file = new File(name: 'big.png', size: 0, error: UPLOAD_ERR_INI_SIZE);

        try {
            new Upload('image', true)->resolve($this->request([ 'image' => $file ]));
            $this->fail("Expected exception");
        } catch (ParameterNotValid $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertFalse($e->missing);
            $this->assertSame("The upload parameter 'image' is not a valid " . File::class . ".", $e->getMessage());
        }
    }

    public function test_empty_file_is_not_valid(): void
    {
        $pathname = tempnam(sys_get_temp_dir(), 'upload');

        try {
            $this->expectException(ParameterNotValid::class);

            new Upload('image')->resolve($this->request([ 'image' => new File(pathname: $pathname) ]));
        } finally {
            unlink($pathname);
        }
    }

    public function test_list_of_files_is_not_valid(): void
    {
        $files = [ 'image' => new FileList([ new File(pathname: __FILE__) ]) ];

        $this->expectException(ParameterNotValid::class);

        new Upload('image')->resolve($this->request($files));
    }

    public function test_request_params_are_ignored(): void
    {
        $request = Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_REQUEST_PARAMS => [ 'image' => 'abc' ],
        ]);

        $actual = new Upload('image', true)->resolve($request);

        $this->assertNull($actual);
    }

    public function test_export(): void
    {
        $parameter = new Upload('image', true);
        $actual = SetStateHelper::export_import($parameter);

        $this->assertEquals($parameter, $actual);
    }

    /**
     * @param array<string, File|FileList> $files
     */
    private function request(array $files): Request
    {
        return Request::from([
            RequestOptions::OPTION_URI => '/',
            RequestOptions::OPTION_FILES => new FileList($files),
        ]);
    }
}
