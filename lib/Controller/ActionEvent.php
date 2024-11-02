<?php

namespace ICanBoogie\Routing\Controller;

use ICanBoogie\Event;
use ICanBoogie\Routing\ControllerAbstract;

/**
 * Listener may use this event to alter the result returned by the `action()` method.
 */
class ActionEvent extends Event
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
