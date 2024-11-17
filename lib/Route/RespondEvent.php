<?php

namespace ICanBoogie\Routing\Route;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;

/**
 * Listeners may use this event to alter the response before it is returned by the dispatcher.
 */
final class RespondEvent extends Event
{
    public function __construct(
        Route $sender,
        public readonly Request $request,
        public Response &$response
    ) {
        parent::__construct($sender);
    }
}
