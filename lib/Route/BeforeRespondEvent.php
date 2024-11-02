<?php

namespace ICanBoogie\Routing\Route;

use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;

/**
 * Listeners may use this event to provide a response to the request before the route is mapped. The event is usually
 * used by third parties to redirect requests or provide cached responses.
 */
final class BeforeRespondEvent extends Event
{
    public ?Response $response;

    public function __construct(
        Route $sender,
        public readonly Request $request,
        ?Response &$response = null
    ) {
        $this->response = &$response;

        parent::__construct($sender);
    }
}
