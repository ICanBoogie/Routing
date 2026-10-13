<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Decoder;

/**
 * Binds a parameter of an action to the raw body of the request, the `SplFileObject` of
 * `Request::$body_stream`, usually `php://input`.
 *
 * With a {@see Decoder}, the parameter is the object the decoder makes from the body, and the
 * decoder receives the file object. The `using` argument is optional when the type of the parameter
 * is a decoder itself. A method has at most one `#[Body]`.
 *
 * Decoded data is also available through {@see Part}, a form or a JSON object; the body is for what
 * `Part` can't provide, e.g. XML, a custom format, or a large upload to process without loading it in
 * memory.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Body
{
    /**
     * @param class-string<Decoder>|null $using
     *     A {@see Decoder} that turns the body into an object.
     */
    public function __construct(
        public ?string $using = null,
    ) {
    }
}
