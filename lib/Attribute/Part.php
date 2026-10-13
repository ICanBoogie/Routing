<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;

/**
 * Binds a parameter of an action to a named piece of the request body, a field of a form or a
 * property of a JSON object.
 *
 * The value is cast to the type of the parameter, `int`, `float`, `string`, `bool`, or a backed
 * enum. A missing value is the default value of the parameter, or `null` if it is nullable,
 * otherwise the request is not valid.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Part
{
    /**
     * @param non-empty-string|null $name
     *     Name of the piece in the body, defaults to the name of the parameter.
     */
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
