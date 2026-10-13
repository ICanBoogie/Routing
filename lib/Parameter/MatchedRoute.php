<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Parameter;
use ICanBoogie\Routing\Route;

/**
 * Provides the route that matched the request, which is found in the request's context.
 */
final readonly class MatchedRoute implements Parameter
{
    /**
     * @param array<string, mixed> $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self();
    }

    public function resolve(Request $request): Route
    {
        return $request->context->get(Route::class);
    }
}
