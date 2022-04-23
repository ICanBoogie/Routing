<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        array|object $query_params = null,
    ): string;
}
