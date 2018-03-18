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
use ICanBoogie\HTTP\Response;
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
    protected function get_action(): string
    {
        $action = $this->route->action;

	    if (empty($action))
	    {
		    throw new ActionNotDefined("Action not defined for route {$this->route->id}.");
	    }

	    return $action;
    }

    /**
     * Dispatch the request to the appropriate method.
     *
     * The {@link $request} property is initialized.
     *
     * @param Request $request
     *
     * @return Response|mixed
     */
    protected function action(Request $request)
    {
        return $this->resolve_action($request)();
    }

    /**
     * Resolves the action into a callable.
     *
     * @param Request $request
     *
     * @return callable
     */
    protected function resolve_action(Request $request): callable
    {
        $action = $this->action;
        $method = $this->resolve_action_method($action, $request);
        $args = $this->resolve_action_args($action, $request);

        return function () use ($method, $args) {

            return $this->$method(...$args);

        };
    }

    protected function resolve_action_method(string $action, Request $request): string
    {
        $action = \strtr($action, '-', '_');
        $method = 'action_' . \strtolower($request->method) . '_' . $action;

        if (\method_exists($this, $method))
        {
            return $method;
        }

        $method = 'action_any_' . $action;

        if (\method_exists($this, $method))
        {
            return $method;
        }

        return 'action_' . $action;
    }

    protected function resolve_action_args(string $action, Request $request): array
    {
        return \array_values($request->path_params);
    }
}
