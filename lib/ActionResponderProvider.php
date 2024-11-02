<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

/**
 * Provides a Responder for the specified Action.
 */
interface ActionResponderProvider
{
    public function responder_for_action(string $action): ?Responder;
}
