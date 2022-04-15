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

use RuntimeException;

/**
 * Patchable helpers.
 *
 * @method static string contextualize(string $pathname)
 * @method static string decontextualize(string $pathname)
 * @method static string absolutize_url(string $url)
 */
final class Helpers
{
	/**
	 * @var array<string, callable>
	 */
	private static array $mapping = [

		'contextualize'   => [ __CLASS__, 'default_contextualize' ],
		'decontextualize' => [ __CLASS__, 'default_decontextualize' ],
		'absolutize_url'  => [ __CLASS__, 'default_absolutize_url' ]

	];

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @uses default_contextualize
	 * @uses default_decontextualize
	 * @uses default_absolutize_url
	 */
	public static function __callStatic(string $name, array $arguments): mixed
	{
		return (self::$mapping[$name])(...$arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param callable $callback Callback.
	 *
	 * @throws RuntimeException is attempt to patch an undefined function.
	 */
	// @codeCoverageIgnoreStart
	public static function patch(string $name, callable $callback): void
	{
		if (empty(self::$mapping[$name]))
		{
			throw new RuntimeException("Undefined patchable: $name.");
		}

		self::$mapping[$name] = $callback;
	}
	// @codeCoverageIgnoreEnd

	/*
	 * Default implementations
	 */

	private static function default_contextualize(string $pathname): string
	{
		return $pathname;
	}

	private static function default_decontextualize(string $pathname): string
	{
		return $pathname;
	}

	private static function default_absolutize_url(string $url): string
	{
		return 'http://' . $_SERVER['HTTP_HOST'] . $url;
	}
}
