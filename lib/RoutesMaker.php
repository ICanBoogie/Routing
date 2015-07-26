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
	 * @param string $name
	 * @param string $controller
	 * @param array $actions Action templates.
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
	static public function actions($name, $controller, $actions, array $options = [])
	{
		$options = self::normalize_options($options);
		$actions = static::filter_actions($actions, $options);
		$actions = static::resolve_patterns($name, $actions, $options);

		$options_as = $options['as'];
		$routes = [];

		foreach ($actions as $action => list($pattern, $via))
		{
			$as = empty($options_as[$action]) ? "{$name}:{$action}" : $options_as[$action];

			$routes[$as] = [

				'pattern' => $pattern,
				'controller' => "{$controller}#{$action}",
				'via' => $via,
				'as' => $as

			];
		}

		return $routes;
	}

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
	 * - `actions`: Additional actions templates.
	 *
	 * @return array
	 */
	static public function resource($name, $controller, array $options = [])
	{
		$options = static::normalize_options($options);
		$actions = array_merge(static::get_resource_actions(), $options['actions']);

		return static::actions($name, $controller, $actions, $options);
	}

	/**
	 * Normalizes options.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	static protected function normalize_options(array $options)
	{
		return $options + [

			'id_name' => 'id',
			'id_regex' => '\d+',
			'only' => [ ],
			'except' => [ ],
			'as' => [ ],
			'actions' => [ ]

		];
	}

	/**
	 * Returns default resource actions.
	 *
	 * @return array
	 */
	static protected function get_resource_actions()
	{
		return [

			'index'   => [ '/{name}',           Request::METHOD_GET ],
			'create'  => [ '/{name}/create',    Request::METHOD_GET ],
			'store'   => [ '/{name}',           Request::METHOD_POST ],
			'show'    => [ '/{name}/{id}',      Request::METHOD_GET ],
			'edit'    => [ '/{name}/{id}/edit', Request::METHOD_GET ],
			'update'  => [ '/{name}/{id}',      [ Request::METHOD_PUT, Request::METHOD_PATCH ] ],
			'destroy' => [ '/{name}/{id}',      Request::METHOD_DELETE ]

		];
	}

	/**
	 * Filters actions according to only/except options.
	 *
	 * @param array $actions
	 * @param array $options
	 *
	 * @return array
	 */
	static protected function filter_actions(array $actions, array $options = [])
	{
		if ($options['only'])
		{
			$actions = array_intersect_key($actions, array_flip((array) $options['only']));
		}

		if ($options['except'])
		{
			$actions = array_diff_key($actions, array_flip((array) $options['except']));
		}

		return $actions;
	}

	/**
	 * Replaces pattern placeholders.
	 *
	 * @param string $name
	 * @param array $actions
	 * @param array $options
	 *
	 * @return array
	 */
	static protected function resolve_patterns($name, array $actions, $options)
	{
		$id = "<{$options['id_name']}:{$options['id_regex']}>";
		$replace = [ '{name}' => $name, '{id}' => $id ];

		foreach ($actions as $action => &$template)
		{
			$template[0] = strtr($template[0], $replace);
		}

		return $actions;
	}
}
