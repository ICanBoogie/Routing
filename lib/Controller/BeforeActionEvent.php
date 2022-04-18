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

use ICanBoogie\Event;
use ICanBoogie\Routing\ControllerAbstract;

/**
 * Event class for the `ICanBoogie\Routing\Controller::action:before` event.
 *
 * Event hooks may use this event to alter the controller before the action is invoked, or provide
 * a result and thus cancel the action.
 */
class BeforeActionEvent extends Event
{
    public const TYPE = 'action:before';

    /**
     * Reference to the result.
     */
    public mixed $result;

    public function __construct(ControllerAbstract $target, mixed &$result)
    {
        $this->result = &$result;

        parent::__construct($target, self::TYPE);
    }
}
