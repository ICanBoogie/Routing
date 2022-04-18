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
 * A responder provider that supports mutations.
 */
interface MutableActionResponderProvider extends ActionResponderProvider
{
    /**
     * Add a responder for an action.
     */
    public function add_responder(string $action, Responder $responder): void;
}
