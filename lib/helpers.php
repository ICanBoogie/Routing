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
 * Contextualize a pathname.
 *
 * @param string $pathname
 *
 * @return string
 */
function contextualize($pathname)
{
	return Helpers::contextualize($pathname);
}

/**
 * Decontextualize a pathname.
 *
 * @param string $pathname
 *
 * @return string
 */
function decontextualize($pathname)
{
	return Helpers::decontextualize($pathname);
}


/**
 * Patchable helpers.
 */
class Helpers
{
	static private $jumptable = array
	(
		'contextualize' => array(__CLASS__, 'contextualize'),
		'decontextualize' => array(__CLASS__, 'decontextualize')
	);

	/**
	 * Calls the callback of a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param array $arguments Arguments.
	 *
	 * @return mixed
	 */
	static public function __callstatic($name, array $arguments)
	{
		return call_user_func_array(self::$jumptable[$name], $arguments);
	}

	/**
	 * Patches a patchable function.
	 *
	 * @param string $name Name of the function.
	 * @param collable $callback Callback.
	 *
	 * @throws \RuntimeException is attempt to patch an undefined function.
	 */
	static public function patch($name, $callback)
	{
		if (empty(self::$jumptable[$name]))
		{
			throw new \RuntimeException("Undefined patchable: $name.");
		}

		self::$jumptable[$name] = $callback;
	}

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
}