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

/**
 * Resource oriented controller implementation.
 */
trait ResourceTrait
{
    use ActionTrait
    {
        ActionTrait::resolve_action_method as action_resolve_action_method;
    }

    static protected $resource_actions = [ 'index', 'create', 'store', 'show', 'edit', 'update', 'destroy' ];

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
        if (in_array($action, self::$resource_actions))
        {
            return $action;
        }

        return $this->action_resolve_action_method($action, $request);
    }
}
