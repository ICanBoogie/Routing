<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Parameter;

/**
 * Provides the current request.
 */
final readonly class CurrentRequest implements Parameter
{
    /**
     * @param array<string, mixed> $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self();
    }

    public function resolve(Request $request): Request
    {
        return $request;
    }
}
