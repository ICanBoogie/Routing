<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

interface Middleware
{
	/**
	 * Creates a responder, that can forward a request to a next responder.
	 */
	public function responder(Responder $next): Responder;
}
