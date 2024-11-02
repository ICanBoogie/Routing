<?php

namespace ICanBoogie\Routing;

interface UrlGenerator
{
    /**
     * @phpstan-param string|(callable(Route): bool) $predicate_or_id_or_action
     *
     * @param array<string, mixed>|object|null $path_params
     *     Parameters that reference placeholders in the route pattern.
     * @param array<string, mixed>|object|null $query_params
     *     Parameters for the query string.
     */
    public function generate_url(
        string|callable $predicate_or_id_or_action,
        array|object|null $path_params = null,
        array|object|null $query_params = null,
    ): string;
}
