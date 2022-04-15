<?php

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\MutableResponderProvider;

/**
 * Provides controllers from runtime configuration.
 */
final class Mutable implements MutableResponderProvider
{
	/**
	 * @var array<string, Responder>
	 *     Where _key_ is an action and _value_ a responder.
	 */
	private array $map = [];

	public function add_responder(string $action, Responder $responder): void
	{
		$this->map[$action] = $responder;
	}

	public function responder_for_action(string $action): ?Responder
	{
		return $this->map[$action] ?? null;
	}
}
