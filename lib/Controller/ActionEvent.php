<?php

namespace ICanBoogie\Routing\Controller;

use Closure;
use ICanBoogie\Event;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerAbstract;
use Stringable;

/**
 * Listener may use this event to alter the result returned by the `action()` method.
 */
class ActionEvent extends Event
{
    public function __construct(
        ControllerAbstract $sender,
        public Response|Closure|Stringable|string|null &$result,
    ) {
        parent::__construct($sender);
    }
}
