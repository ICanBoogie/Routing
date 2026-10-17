<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Decoder;

/**
 * Binds a parameter of an action to a named piece of the request body, a field of a form or a
 * property of a JSON object.
 *
 * The pieces are those of {@see \ICanBoogie\HTTP\Request::$request_params}, which PHP fills from
 * `$_POST` for a form. A JSON body is **not** decoded by `icanboogie/http`: decode it into
 * `request_params` (e.g. in a middleware) or `#[Part]` finds nothing, see
 * docs/StatelessControllers.md.
 *
 * `#[Part]` suits flat values. To build an object from a JSON body, e.g. a command, use {@see Body}
 * with a {@see Decoder}.
 *
 * The value is cast to the type of the parameter, `int`, `float`, `string`, `bool`, or a backed
 * enum. A missing value is the default value of the parameter, or `null` if it is nullable,
 * otherwise the request is not valid.
 *
 * A parameter typed `array` receives a list of values, e.g. `tags[]` of a form. The elements are
 * cast to the type given by `of`; without it they are provided as the client sent them, unvalidated.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Part
{
    /**
     * @param non-empty-string|null $name
     *     Name of the piece in the body, defaults to the name of the parameter.
     * @param class-string<Decoder>|null $using
     *     A {@see Decoder} that turns the value into an object. It is optional when the type of the
     *     parameter is a {@see Decoder} itself.
     * @param ElementType|class-string<\BackedEnum>|null $of
     *     Type of the elements of an `array` parameter, defaults to {@see ElementType::Mixed}. It
     *     is not valid for another type of parameter.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $using = null,
        public ElementType|string|null $of = null,
    ) {
    }
}
