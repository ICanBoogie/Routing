<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

/**
 * Provides a Responder for the specified Action.
 */
interface ActionResponderProvider
{
	public function responder_for_action(string $action): ?Responder;
}
