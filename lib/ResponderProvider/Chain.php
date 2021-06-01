<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ResponderProvider;

/**
 * Tries a chain of controller providers until one provides a controller.
 */
final class Chain implements ResponderProvider
{
	/**
	 * @var iterable<ResponderProvider>
	 */
	private iterable $chain;

	/**
	 * @param iterable<ResponderProvider> $providers
	 */
	public function __construct(ResponderProvider ...$providers)
	{
		$this->chain = $providers;
	}

	public function responder_for_action(string $action): ?Responder
	{
		foreach ($this->chain as $provider) {
			$responder = $provider->responder_for_action($action);

			if ($responder) {
				return $responder;
			}
		}

		return null;
	}
}
