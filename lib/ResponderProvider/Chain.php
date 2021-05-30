<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ResponderProvider;
use InvalidArgumentException;

use function sprintf;

/**
 * Tries a chain of controller providers until one provides a controller.
 */
final class Chain implements ResponderProvider
{
	/**
	 * @var ResponderProvider[]
	 */
	private array $chain = [];

	/**
	 * @param ResponderProvider[] $chain
	 */
	public function __construct(iterable $chain)
	{
		foreach ($chain as $provider) {
			if (!$provider instanceof ResponderProvider) {
				throw new InvalidArgumentException(sprintf("Provider needs to implement %s.", ResponderProvider::class));
			}

			$this->chain[] = $provider;
		}
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
