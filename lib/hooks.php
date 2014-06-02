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

use ICanBoogie\Routes;

class Hooks
{
	/**
	 * Returns the route collection.
	 *
	 * @return \ICanBoogie\Routes
	 */
	static public function core_get_routes(\ICanBoogie\Core $core)
	{
		static $routes;

		return $routes ?: ($routes = Routes::get());
	}
}