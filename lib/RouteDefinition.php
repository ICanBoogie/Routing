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

use function ICanBoogie\format;

/**
 * The class defines options that can be used to define a route as well as means to normalize and
 * validate this definition.
 */
class RouteDefinition
{
	/**
	 * Pattern of the route.
	 */
	public const PATTERN = 'pattern';

	/**
	 * A controller class name (with an optional action) or a callable.
	 */
	public const CONTROLLER = 'controller';

	/**
	 * The controller action.
	 */
	public const ACTION = 'action';

	/**
	 * An identifier.
	 */
	public const ID = 'id';

	/**
	 * A redirection target.
	 */
	public const LOCATION = 'location';

	/**
	 * Request method(s) accepted by the route.
	 */
	public const VIA = 'via';

	/**
	 * Route constructor, a class name for now.
	 */
	public const CONSTRUCTOR = 'class';

	/**
	 * Normalizes a route definition.
	 *
	 * @param array<string, mixed> $definition
	 */
	static public function normalize(array &$definition): void
	{
		if (empty($definition[self::VIA]))
		{
			$definition[self::VIA] = Request::METHOD_ANY;
		}
	}

	/**
	 * Ensures that a route definition has an identifier and generates one if required.
	 *
	 * @param array<string, mixed> $definition
	 *
	 * @return string The route identifier.
	 */
	static public function ensure_has_id(array &$definition): string
	{
		if (empty($definition[self::ID]))
		{
			$definition[self::ID] = self::generate_anonymous_id();
		}

		return $definition[self::ID];
	}

	static private $anonymous_id_count;

	/**
	 * Generates an anonymous route identifier.
	 *
	 * @return string
	 */
	static private function generate_anonymous_id(): string
	{
		return 'anonymous_route_' . ++self::$anonymous_id_count;
	}

	/**
	 * Asserts that a route definition is valid.
	 *
	 * @param array<string, mixed> $definition
	 *
	 * @throws PatternNotDefined when the pattern is not defined
	 * @throws ControllerNotDefined when both controller and location are not defined.
	 */
	static public function assert_is_valid(array $definition): void
	{
		if (empty($definition[self::PATTERN]))
		{
			throw new PatternNotDefined(format("Pattern is not defined: !route", [

				'route' => $definition

			]));
		}

		if (empty($definition[self::CONTROLLER]) && empty($definition[self::LOCATION]))
		{
			throw new ControllerNotDefined(format("Controller is not defined: !route", [

				'route' => $definition

			]));
		}
	}

	/**
	 * No instance should be created from this class.
	 *
	 * @codeCoverageIgnore
	 */
	private function __construct()
	{

	}
}
