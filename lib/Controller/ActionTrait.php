<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Controller;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\ActionNotDefined;
use ICanBoogie\Routing\Route;

/**
 * Action controller implementation.
 *
 * @property-read Route $route
 * @property-read string $action The action being executed.
 */
trait ActionTrait
{
    /**
     * Returns the action being executed.
     *
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
     * @return \ICanBoogie\HTTP\Response|mixed
     */
    protected function action(Request $request)
    {
        $callable = $this->resolve_action($request);

        return $callable();
    }

	/**
	 * Whether the action has a direct method match.
	 *
	 * @param string $action
	 *
	 * @return bool `true` if the action has a direct method match, `false` otherwise.
	 */
	protected function is_action_method($action)
	{
		return false;
	}

    /**
     * Resolves the action into a callable.
     *
     * @param Request $request
     *
     * @return callable
     */
    protected function resolve_action(Request $request)
    {
        $action = $this->action;

        if (!$action)
        {
            throw new ActionNotDefined("Action not defined in route {$this->route->id}.");
        }

        $method = $this->resolve_action_method($action, $request);
        $args = $this->resolve_action_args($action, $request);

        return function() use ($method, $args)
        {
            return call_user_func_array([ $this, $method ], $args);
        };
    }

    /**
     * Resolves the method associated with the action.
     *
     * @param string $action Action name.
     * @param Request $request
     *
     * @return string The method name.
     */
    protected function resolve_action_method($action, Request $request)
    {
        $action = strtr($action, '-', '_');

	    if ($this->is_action_method($action))
	    {
		    return $action;
	    }

        $method = 'action_' . strtolower($request->method) . '_' . $action;

        if (method_exists($this, $method))
        {
            return $method;
        }

        $method = 'action_any_' . $action;

        if (method_exists($this, $method))
        {
            return $method;
        }

        return $method = 'action_' . $action;
    }

    /**
     * Resolves the arguments associated with the action.
     *
     * @param string $action Action name.
     * @param Request $request
     *
     * @return array The arguments for the action.
     */
    protected function resolve_action_args($action, Request $request)
    {
        return $request->path_params;
    }
}
