<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Decoder;

/**
 * Binds a parameter of an action to a path parameter, a placeholder of the pattern.
 *
 * A scalar or an enum parameter is a path parameter without it, the attribute is only needed when
 * the name of the placeholder is not the name of the parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Path
{
    /**
     * @param non-empty-string|null $name
     *     Name of the placeholder, defaults to the name of the parameter.
     * @param class-string<Decoder>|null $using
     *     A {@see Decoder} that turns the value into an object. It is optional when the type of the
     *     parameter is a {@see Decoder} itself.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $using = null,
    ) {
    }
}
