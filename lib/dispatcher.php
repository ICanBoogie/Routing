<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\HTTP\RedirectResponse;
use ICanBoogie\HTTP\Request;

/**
 * Dispatches requests among the defined routes.
 *
 * <pre>
 * use ICanBoogie\HTTP\Dispatcher;
 *
 * $dispatcher = new Dispatcher(array('routes' => 'ICanBoogie\RouteDispatcher'));
 * </pre>
 */
class RouteDispatcher implements \ICanBoogie\HTTP\IDispatcher
{
	public function __invoke(Request $request)
	{
		$path = rtrim(Route::decontextualize($request->normalized_path), '/');

		#
		# we trim ending '/' but we need one for index.
		#

		if (!$path)
		{
			$path = '/';
		}

		$route = Routes::get()->find($path, $captured, $request->method);

		if (!$route)
		{
			return;
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;

		if ($route->location)
		{
			return new RedirectResponse(Route::contextualize($route->location), 302);
		}

		return $route($request);
	}

	public function rescue(\Exception $exception)
	{
		throw $exception;
	}
}