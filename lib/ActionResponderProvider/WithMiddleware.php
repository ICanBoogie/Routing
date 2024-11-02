<?php

namespace ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\MiddlewareCollection;
use ICanBoogie\Routing\ActionResponderProvider;

/**
 * Decorates responders with middleware.
 */
final readonly class WithMiddleware implements ActionResponderProvider
{
    public function __construct(
        private ActionResponderProvider $next,
        private MiddlewareCollection $middleware,
    ) {
    }

    public function responder_for_action(string $action): ?Responder
    {
        $responder = $this->next->responder_for_action($action);

        if (!$responder) {
            return null;
        }

        return $this->middleware->chain($responder);
    }
}
