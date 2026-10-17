<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\HTTP\File;

/**
 * Binds a parameter of an action to a file uploaded with a `multipart/form-data` form.
 *
 * The files are those of {@see \ICanBoogie\HTTP\Request::$files}. The parameter must be typed
 * {@see File}. The attribute is optional, a parameter typed {@see File} is bound to the file with
 * the name of the parameter; use it to give another name. A file that was not sent, including a form field left empty by the browser
 * (`UPLOAD_ERR_NO_FILE`), is missing: `null` if the parameter is nullable, otherwise the request is
 * not valid. A file that failed to upload, e.g. because it exceeds `upload_max_filesize`, or that
 * is empty, is not valid.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Upload
{
    /**
     * @param non-empty-string|null $name
     *     Name of the file in the form, defaults to the name of the parameter.
     */
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
