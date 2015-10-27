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
class RouteMaker
{
	const ACTION_INDEX = 'index';
	const ACTION_NEW = 'new';
	const ACTION_CREATE = 'create';
	const ACTION_SHOW = 'show';
	const ACTION_EDIT = 'edit';
	const ACTION_UPDATE = 'update';
	const ACTION_DELETE = 'delete';

	const OPTION_ID_NAME = 'id_name';
	const OPTION_ID_REGEX = 'id_regex';
	const OPTION_ONLY = 'only';
	const OPTION_EXCEPT = 'except';
	const OPTION_AS = 'as';
	const OPTION_ACTIONS = 'actions';

	const SEPARATOR = ':';
	const CONTROLLER_ACTION_SEPARATOR = '#';

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

		$options_as = $options[self::OPTION_AS];
		$routes = [];

		foreach ($actions as $action => list($pattern, $via))
		{
			$as = empty($options_as[$action]) ? $name . self::SEPARATOR . $action : $options_as[$action];

			$routes[$as] = [

				RouteDefinition::PATTERN => $pattern,
				RouteDefinition::CONTROLLER => $controller,
				RouteDefinition::ACTION => $action,
				RouteDefinition::VIA => $via,
				RouteDefinition::ID => $as

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
		$actions = array_merge(static::get_resource_actions(), $options[self::OPTION_ACTIONS]);

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

			self::OPTION_ID_NAME => 'id',
			self::OPTION_ID_REGEX => '\d+',
			self::OPTION_ONLY => [ ],
			self::OPTION_EXCEPT => [ ],
			self::OPTION_AS => [ ],
			self::OPTION_ACTIONS => [ ]

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

			self::ACTION_INDEX  => [ '/{name}',           Request::METHOD_GET ],
			self::ACTION_NEW    => [ '/{name}/new',       Request::METHOD_GET ],
			self::ACTION_CREATE => [ '/{name}',           Request::METHOD_POST ],
			self::ACTION_SHOW   => [ '/{name}/{id}',      Request::METHOD_GET ],
			self::ACTION_EDIT   => [ '/{name}/{id}/edit', Request::METHOD_GET ],
			self::ACTION_UPDATE => [ '/{name}/{id}',      [ Request::METHOD_PUT, Request::METHOD_PATCH ] ],
			self::ACTION_DELETE => [ '/{name}/{id}',      Request::METHOD_DELETE ]

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
		if ($options[self::OPTION_ONLY])
		{
			$actions = array_intersect_key($actions, array_flip((array) $options[self::OPTION_ONLY]));
		}

		if ($options[self::OPTION_EXCEPT])
		{
			$actions = array_diff_key($actions, array_flip((array) $options[self::OPTION_EXCEPT]));
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
		$id = "<{$options[self::OPTION_ID_NAME]}:{$options[self::OPTION_ID_REGEX]}>";
		$replace = [ '{name}' => $name, '{id}' => $id ];

		foreach ($actions as $action => &$template)
		{
			$template[0] = strtr($template[0], $replace);
		}

		return $actions;
	}
}
