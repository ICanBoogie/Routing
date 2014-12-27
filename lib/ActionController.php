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
use ICanBoogie\Routing\ActionController\ActionEvent;
use ICanBoogie\Routing\ActionController\BeforeActionEvent;

/**
 * Base class for action controllers.
 *
 * @package ICanBoogie\Routing
 *
 * @property-read string $action The action being executed.
 */
class ActionController extends Controller
{
	/**
	 * The request.
	 *
	 * @var Request
	 */
	protected $request;

	/**
	 * @return string
	 */
	protected function get_action()
	{
		return $this->route->action;
	}

	/**
	 * Dispatch the request to the appropriate method.
	 *
	 * The {@link $request} property is initialized.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response
	 */
	public function __invoke(Request $request)
	{
		$this->request = $request;
		$action = $this->action;

		if (!$action)
		{
			throw new ActionNotDefined("Action not defined in route.");
		}

		$method_name = strtolower($request->method) . '_' . $action;
		$method_args = $request->path_params;

		if (!method_exists($this, $method_name))
		{
			$method_name = 'any_' . $action;
		}

		#

		$response = null;
		$route = $this->request->context->route;

		new BeforeActionEvent($this, $action, $response, $route, $request);

		if (!$response)
		{
			$response = call_user_func_array([ $this, $method_name ], $method_args);
		}

		new ActionEvent($this, $action, $response, $route, $request);

		return $response;
	}
}
