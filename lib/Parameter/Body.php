<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Parameter;
use SplFileObject;

/**
 * Provides the raw body of the request, the file object found in {@see Request::$body_stream}.
 *
 * The body is never missing, an empty body is an empty file: a request without a stream, e.g. one
 * created by a test, provides an empty file object.
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
        return self::of($request);
    }

    /**
     * Returns the body of the request.
     *
     * @internal
     */
    public static function of(Request $request): SplFileObject
    {
        return $request->body_stream ?? new SplFileObject('php://memory', 'r');
    }
}
