<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\RouteProvider;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use InvalidArgumentException;

use function array_diff_key;
use function is_array;
use function parse_str;
use function parse_url;

/**
 * A predicate that matches a route against a URI and an optional HTTP method.
 *
 * If the match failed, please disregard the parameters {@link $path}, {@link $path_params},
 * and {@link $query_params}.
 */
final class ByUri
{
    /**
     * @var string
     *     The path extracted from the URI, without query parameters.
     */
    public readonly string $path;

    /**
     * @var array<string|int, string>
     *     Parameters captured from the path info.
     */
    public array $path_params = [];

    /**
     * @var array<string|int, string>
     *     Parameters captured from the query string.
     *     Careful! Parameters matching those captured from the path are discarded.
     */
    public array $query_params = [];

    public function __construct(
        public readonly string $uri,
        public readonly RequestMethod $method = RequestMethod::METHOD_ANY,
    ) {
        $parsed = parse_url($uri);

        if (!is_array($parsed)) {
            throw new InvalidArgumentException("Unable to parse URI: $uri.");
        }

        $path = $parsed['path'] ?? null;

        if (!$path) {
            throw new InvalidArgumentException("Unable to extract path from URI: $uri.");
        }

        $this->path = $path;

        $query = $parsed['query'] ?? null;

        if ($query) {
            parse_str($query, $this->query_params);
        }
    }

    public function __invoke(Route $route): bool
    {
        if (
            !$route->pattern->matches($this->path, $this->path_params) ||
            !$route->method_matches($this->method)
        ) {
            return false;
        }

        if ($this->query_params && $this->path_params) {
            $this->query_params = array_diff_key($this->query_params, $this->path_params);
        }

        return true;
    }
}
