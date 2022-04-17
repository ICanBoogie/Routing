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

use ICanBoogie\Routing\RouteMaker\Basics;
use ICanBoogie\Routing\RouteMaker\Options;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_merge;
use function strtr;

/**
 * Makes route definitions.
 */
final class RouteMaker
{
	/*
	 * Unqualified actions.
	 */
	public const ACTION_LIST = 'list';
	public const ACTION_NEW = 'new';
	public const ACTION_CREATE = 'create';
	public const ACTION_SHOW = 'show';
	public const ACTION_EDIT = 'edit';
	public const ACTION_UPDATE = 'update';
	public const ACTION_DELETE = 'delete';

	/**
	 * @param array<string, Basics> $basics Action templates.
	 *     If {@link Options::$ids} is not specified for an action, the qualified action is used as identifier.
	 *
	 * @return Route[]
	 */
	public static function actions(string $name, array $basics, Options $options = null): array
	{
		$options ??= new Options();
		$basics = array_merge($basics, $options->basics);
		$basics = self::filter($basics, $options);
		$basics = self::resolve_patterns($name, $basics, $options);

		$as = $options->as;
		$ids = $options->ids;
		$routes = [];

		foreach ($basics as $action => $basic)
		{
			$qualified_action = $as[$action] ?? $name . Route::ACTION_SEPARATOR . $action;

			$routes[] = new Route(
				pattern: $basic->pattern,
				action: $qualified_action,
				methods: $basic->methods,
				id: $ids[$action] ?? $qualified_action,
			);
		}

		return $routes;
	}

	/**
	 * Makes route definitions for a resource.
	 *
	 * @return Route[]
	 */
	public static function resource(string $name, Options $options = null): array
	{
		return self::actions($name, self::default_resource_actions(), $options);
	}

	/**
	 * Returns default resource actions.
	 *
	 * @return array<string, Basics>
	 */
	private static function default_resource_actions(): array
	{
		return [

			self::ACTION_LIST   => new Basics(Basics::PATTERN_LIST,   Basics::METHODS_LIST),
			self::ACTION_NEW    => new Basics(Basics::PATTERN_NEW,    Basics::METHODS_NEW),
			self::ACTION_CREATE => new Basics(Basics::PATTERN_CREATE, Basics::METHODS_CREATE),
			self::ACTION_SHOW   => new Basics(Basics::PATTERN_SHOW,   Basics::METHODS_SHOW),
			self::ACTION_EDIT   => new Basics(Basics::PATTERN_EDIT,   Basics::METHODS_EDIT),
			self::ACTION_UPDATE => new Basics(Basics::PATTERN_UPDATE, Basics::METHODS_UPDATE),
			self::ACTION_DELETE => new Basics(Basics::PATTERN_DELETE, Basics::METHODS_DELETE),

		];
	}

	/**
	 * Filters actions according to only/except options.
	 *
	 * @param array<string, Basics> $basics
	 *
	 * @return array<string, Basics>
	 */
	private static function filter(array $basics, Options $options): array
	{
		if ($options->only)
		{
			$basics = array_intersect_key($basics, array_flip($options->only));
		}

		if ($options->except)
		{
			$basics = array_diff_key($basics, array_flip($options->except));
		}

		return $basics;
	}

	/**
	 * Replaces pattern placeholders.
	 *
	 * @param array<string, Basics> $basics
	 *
	 * @return array<string, Basics>
	 */
	private static function resolve_patterns(string $name, array $basics, Options $options): array
	{
		$id = "<$options->id_name:$options->id_regex>";
		$replace = [ Basics::PLACEHOLDER_NAME => $name, Basics::PLACEHOLDER_ID => $id ];

		foreach ($basics as $basic)
		{
			$basic->pattern = strtr($basic->pattern, $replace);
		}

		return $basics;
	}
}
