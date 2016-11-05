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

/**
 * Patchable helpers.
 *
 * @method static string contextualize() contextualize($pathname)
 * @method static string decontextualize() decontextualize($pathname)
 * @method static string absolutize_url() absolutize_url($url)
 */
class Helpers
{
	static private $mapping = [

		'contextualize'   => [ __CLASS__, 'default_contextualize' ],
		'decontextualize' => [ __CLASS__, 'default_decontextualize' ],
		'absolutize_url'  => [ __CLASS__, 'default_absolutize_url' ]

	];

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param array $arguments Arguments.
	 *
	 * @return mixed
	 */
	static public function __callStatic($name, array $arguments)
	{
		$method = self::$mapping[$name];

		return $method(...$arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param callable $callback Callback.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	// @codeCoverageIgnoreStart
	static public function patch($name, $callback)
	{
		if (empty(self::$mapping[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$mapping[$name] = $callback;
	}
	// @codeCoverageIgnoreEnd

	/*
	 * Default implementations
	 */

	static protected function default_contextualize($pathname)
	{
		return $pathname;
	}

	static protected function default_decontextualize($pathname)
	{
		return $pathname;
	}

	static protected function default_absolutize_url($url)
	{
		return 'http://' . $_SERVER['HTTP_HOST'] . $url;
	}
}
