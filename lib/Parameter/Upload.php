<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\File;
use ICanBoogie\HTTP\FileList;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Parameter;

use function iterator_to_array;

use const UPLOAD_ERR_NO_FILE;

/**
 * Provides a file of {@see Request::$files}.
 *
 * A file that was not sent, or that the browser left empty (`UPLOAD_ERR_NO_FILE`), is missing:
 * `null` if the parameter is nullable, otherwise the request is not valid. A file that failed to
 * upload, or that is empty, is not valid.
 */
final readonly class Upload implements Parameter
{
    /**
     * @param array{ name: non-empty-string, nullable: bool } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param non-empty-string $name
     *     Name of the file in the form.
     * @param bool $nullable
     *     Whether the parameter accepts `null`, which is the value when the file is missing.
     */
    public function __construct(
        public string $name,
        public bool $nullable = false,
    ) {
    }

    /**
     * @throws ParameterNotValid if the file is missing and the parameter is not nullable, or if the
     *     upload failed.
     */
    public function resolve(Request $request): ?File
    {
        // Reading through the iterator, `FileList::offsetGet()` fails on a nested list (`name[]`).
        /** @var File|FileList|null $file */
        $file = iterator_to_array($request->files)[$this->name] ?? null;

        if ($file === null || $file instanceof File && $file->error === UPLOAD_ERR_NO_FILE) {
            return $this->nullable
                ? null
                : throw new ParameterNotValid('upload', $this->name, File::class, missing: true);
        }

        if (!$file instanceof File || !$file->is_valid) {
            throw new ParameterNotValid('upload', $this->name, File::class);
        }

        return $file;
    }
}
