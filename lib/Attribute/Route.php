<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\HTTP\RequestMethod;

/**
 * Defines a route to a method of a controller.
 *
 * The attribute is repeatable, a method can be reached through multiple routes. Shortcuts exist for
 * each request method, e.g. {@see Get}.
 *
 * The prefixes shared by the methods of a controller are defined with {@see Prefix}.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Route
{
    /**
     * @param string $pattern
     *     Pattern of the route.
     *     It's alright to have an _empty_ pattern as long as the controller has a {@link Prefix}
     *     with a pattern to define the base pattern.
     * @param non-empty-string|null $action
     *     Identifier of a qualified action. e.g. 'articles:show'.
     *     If it is not defined, the action is inferred from the controller and the method.
     * @param RequestMethod|RequestMethod[] $methods
     *     Request method(s) accepted by the route.
     * @param non-empty-string|null $id
     *     Identifier of the route.
     */
    public function __construct(
        public string $pattern,
        public ?string $action = null,
        public RequestMethod|array $methods = RequestMethod::METHOD_ANY,
        public ?string $id = null,
    ) {
    }
}
