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

/**
 * Exception thrown by the {@link ActionController} in attempt to handle a route without action.
 *
 * @package ICanBoogie\Routing
 */
class ActionNotDefined extends \LogicException implements Exception
{

}