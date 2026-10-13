<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\HTTP\RequestMethod;

/**
 * Defines a route that accepts the request method OPTIONS.
 *
 * @see Route
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Options extends Route
{
    /**
     * @inheritDoc
     */
    public function __construct(
        string $pattern = '',
        ?string $action = null,
        ?string $id = null,
    ) {
        parent::__construct($pattern, $action, RequestMethod::METHOD_OPTIONS, $id);
    }
}
