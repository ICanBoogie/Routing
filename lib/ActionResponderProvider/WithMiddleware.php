<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\MiddlewareCollection;
use ICanBoogie\Routing\ActionResponderProvider;

/**
 * Decorates responders with middleware.
 */
final class WithMiddleware implements ActionResponderProvider
{
    public function __construct(
        private readonly ActionResponderProvider $next,
        private readonly MiddlewareCollection $middleware,
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
