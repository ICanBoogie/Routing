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
use ICanBoogie\Routing\MutableActionResponderProvider;

/**
 * Provides controllers from runtime configuration.
 */
final class Mutable implements MutableActionResponderProvider
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
