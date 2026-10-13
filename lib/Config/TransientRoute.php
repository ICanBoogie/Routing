<?php

namespace ICanBoogie\Routing\Config;

use ICanBoogie\HTTP\RequestMethod;

/**
 * Route to a method of a controller, used during configuration.
 *
 * It is what an attribute or the route builder defines, before the prefixes are applied and the
 * action is inferred. It becomes a {@see \ICanBoogie\Routing\Route}, and is not part of the
 * configuration.
 *
 * @internal
 */
final readonly class TransientRoute
{
    /**
     * @param RequestMethod|RequestMethod[] $methods
     */
    public function __construct(
        public string $pattern,
        public string $method,
        public RequestMethod|array $methods = RequestMethod::METHOD_ANY,
        public ?string $action = null,
        public ?string $id = null,
    ) {
    }
}
