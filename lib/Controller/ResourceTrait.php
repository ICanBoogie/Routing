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
    use ActionTrait;

    static protected $resource_actions = [ 'index', 'create', 'store', 'show', 'edit', 'update', 'destroy' ];

    /**
     * Whether the action has a direct method match.
     *
     * **Note:** The action is tested against resource actions.
     *
     * @param string $action
     *
     * @return bool `true` if the action has a direct method match, `false` otherwise.
     */
    protected function is_action_method($action)
    {
        return in_array($action, self::$resource_actions);
    }
}
