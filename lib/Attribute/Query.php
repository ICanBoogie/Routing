<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;

/**
 * Binds a parameter of an action to a value of the query string.
 *
 * The value is cast to the type of the parameter, `int`, `float`, `string`, `bool`, or a backed
 * enum. A missing value is the default value of the parameter, or `null` if it is nullable,
 * otherwise the request is not valid.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Query
{
    /**
     * @param non-empty-string|null $name
     *     Name of the value in the query string, defaults to the name of the parameter.
     */
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
