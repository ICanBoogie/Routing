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
 * @method string contextualize() contextualize($pathname)
 * @method string decontextualize() decontextualize($pathname)
 * @method string absolutize_url() absolutize_url($url)
 */
class Helpers
{
	static private $jumptable = [

		'contextualize'   => [ __CLASS__, 'contextualize' ],
		'decontextualize' => [ __CLASS__, 'decontextualize' ],
		'absolutize_url'  => [ __CLASS__, 'absolutize_url' ]

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
		return call_user_func_array(self::$jumptable[$name], $arguments);
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
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$jumptable[$name] = $callback;
	}
	// @codeCoverageIgnoreEnd

	/*
	 * Default implementations
	 */

	static private function contextualize($pathname)
	{
		return $pathname;
	}

	static private function decontextualize($pathname)
	{
		return $pathname;
	}

	static private function absolutize_url($url)
	{
		return 'http://' . $_SERVER['HTTP_HOST'] . $url;
	}
}
