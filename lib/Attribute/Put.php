<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\HTTP\RequestMethod;

/**
 * Defines a route that accepts the request method PUT.
 *
 * @see Route
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Put extends Route
{
    /**
     * @inheritDoc
     */
    public function __construct(
        string $pattern = '',
        ?string $action = null,
        ?string $id = null,
    ) {
        parent::__construct($pattern, $action, RequestMethod::METHOD_PUT, $id);
    }
}
