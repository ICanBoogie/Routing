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

			'index'   => [ '/{resource}',           Request::METHOD_GET ],
			'create'  => [ '/{resource}/create',    Request::METHOD_GET ],
			'store'   => [ '/{resource}',           Request::METHOD_POST ],
			'show'    => [ '/{resource}/{id}',      Request::METHOD_GET ],
			'edit'    => [ '/{resource}/{id}/edit', Request::METHOD_GET ],
			'update'  => [ '/{resource}/{id}',      [ Request::METHOD_PUT, Request::METHOD_PATCH ] ],
			'destroy' => [ '/{resource}/{id}',      Request::METHOD_DELETE ]

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

		foreach ($actions as $action => list($pattern, $via))
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
