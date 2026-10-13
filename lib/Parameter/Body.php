<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Parameter;
use SplFileObject;

/**
 * Provides the raw body of the request, as a file object opened on `php://input`.
 *
 * The body is never missing, an empty body is an empty file.
 */
final readonly class Body implements Parameter
{
    /**
     * @param array<string, mixed> $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self();
    }

    public function resolve(Request $request): SplFileObject
    {
        return new SplFileObject('php://input', 'r');
    }
}
