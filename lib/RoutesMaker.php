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

/**
 * Makes route definitions.
 */
class RoutesMaker
{
	/**
	 * Makes route definitions for a resource.
	 *
	 * @param string $name
	 * @param string $controller
	 * @param array $options The following options are available:
	 *
	 * - `only`: Only the routes specified are made.
	 * - `except`: The routes specified are excluded.
	 * - `id_name`: Name of the identifier property. Defaults to `id`.
	 * - `id_regex`: Regex of the identifier value. Defaults to `\d+`.
	 * - `as`: Specifies the `as` option of the routes created.
	 *
	 * @return array
	 */
	static public function resource($name, $controller, array $options = [])
	{
		$actions = [

			'index'   => [ Request::METHOD_GET, '/{resource}' ],
			'create'  => [ Request::METHOD_GET, '/{resource}/new' ],
			'store'   => [ Request::METHOD_POST, '/{resource}' ],
			'show'    => [ Request::METHOD_GET, '/{resource}/{id}' ],
			'edit'    => [ Request::METHOD_GET, '/{resource}/{id}/edit' ],
			'update'  => [ [ Request::METHOD_PUT, Request::METHOD_PATCH ], '/{resource}/{id}' ],
			'destroy' => [ Request::METHOD_DELETE, '/{resource}/{id}' ],
		];

		$options += [

			'id_name' => 'id',
			'id_regex' => '\d+',
			'only' => [],
			'except' => [],
			'as' => []

		];

		if ($options['only'])
		{
			$actions = array_intersect_key($actions, array_flip((array) $options['only']));
		}

		if ($options['except'])
		{
			$actions = array_diff_key($actions, array_flip((array) $options['except']));
		}

		$id = "<{$options['id_name']}:{$options['id_regex']}>";
		$replace = [ '{resource}' => $name, '{id}' => $id ];
		$options_as = $options['as'];
		$routes = [];

		foreach ($actions as $action => list($via, $pattern))
		{
			$as = empty($options_as[$action]) ? "{$name}:{$action}" : $options_as[$action];

			$routes[$as] = [

				'pattern' => strtr($pattern, $replace),
				'controller' => "{$controller}#{$action}",
				'via' => $via,
				'as' => $as

			];
		}

		return $routes;
	}
}
