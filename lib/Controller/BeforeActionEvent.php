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
 * Listeners may use this event to alter the controller before the action is invoked, or provide a result and thus
 * cancel the action.
 */
class BeforeActionEvent extends Event
{
    /**
     * Reference to the result.
     */
    public mixed $result;

    public function __construct(ControllerAbstract $sender, mixed &$result)
    {
        $this->result = &$result;

        parent::__construct($sender);
    }
}
