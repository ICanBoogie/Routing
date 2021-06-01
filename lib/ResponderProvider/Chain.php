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
	private iterable $providers;

	/**
	 * @param iterable<ResponderProvider> $providers
	 */
	public function __construct(ResponderProvider ...$providers)
	{
		$this->providers = $providers;
	}

	public function responder_for_action(string $action): ?Responder
	{
		foreach ($this->providers as $provider) {
			$responder = $provider->responder_for_action($action);

			if ($responder) {
				return $responder;
			}
		}

		return null;
	}
}
