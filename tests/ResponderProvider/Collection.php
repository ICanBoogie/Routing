<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ResponderProvider;

final class Collection implements ResponderProvider
{
	public function __construct(array $responders)
	{
	}

	public function responder_for_action(string $action): ?Responder
	{
		// TODO: Implement responder_for_action() method.
	}
}
