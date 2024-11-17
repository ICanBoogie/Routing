<?php

namespace ICanBoogie\Routing\Controller;

use Closure;
use ICanBoogie\Event;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerAbstract;
use Stringable;

/**
 * Listeners may use this event to alter the controller before the action is invoked or provide a result and thus
 * cancel the action.
 */
class BeforeActionEvent extends Event
{
    public function __construct(
        ControllerAbstract $sender,
        public Response|Closure|Stringable|string|null &$result,
    ) {
        parent::__construct($sender);
    }
}
