<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\MiddlewareCollection;
use ICanBoogie\Routing\ResponderProvider;

/**
 * Decorates responders with middleware.
 */
final class WithMiddleware implements ResponderProvider
{
	public function __construct(
		private ResponderProvider $next,
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
