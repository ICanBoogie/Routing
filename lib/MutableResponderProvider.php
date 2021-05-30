<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

/**
 * A responder provider that supports mutations.
 */
interface MutableResponderProvider extends ResponderProvider
{
	/**
	 * Add a responder for an action.
	 */
	public function add_responder(string $action, Responder $responder): void;
}
