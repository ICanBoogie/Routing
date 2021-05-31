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

use ICanBoogie\HTTP\Request;

interface RouteProvider
{
	/**
	 * Provides a route matching the specified parameters.
	 *
	 * @phpstan-param Request::METHOD_* $method
	 *
	 * @param array<string|int, string>|null $path_params Parameters captured from the path info.
	 * @param array<string|int, string>|null $query_params Parameters captured from the query string. Careful!
	 * Parameters already captured from the path are discarded.
	 */
	public function route_for_uri(
		string $uri,
		string $method = Request::METHOD_ANY,
		array &$path_params = null,
		array &$query_params = null
	): ?Route;
}
