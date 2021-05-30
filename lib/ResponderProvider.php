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
 * Mapper from an action to the controller that is application for that action.
 */
interface ResponderProvider
{
	public function responder_for_action(string $action): ?Responder;
}
