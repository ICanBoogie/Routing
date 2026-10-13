<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

/**
 * Provides a {@see Responder} for specified actions.
 */
interface ActionResponderProvider
{
    /**
     * Returns the {@see Responder} for the specified action, if any.
     *
     * @param non-empty-string $action
     */
    public function responder_for_action(string $action): ?Responder;
}
