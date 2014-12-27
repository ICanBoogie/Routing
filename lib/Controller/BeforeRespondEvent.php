<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Controller;

use ICanBoogie\Event;
use ICanBoogie\Routing\Controller;

/**
 * Event class for the `ICanBoogie\Routing\Controller::respond:before` event.
 *
 * Event hooks may use this event to alter the controller before it obtain a response.
 *
 * @package ICanBoogie\Routing\Controller
 */
class BeforeRespondEvent extends Event
{
	/**
	 * Reference to the response.
	 *
	 * @var \ICanBoogie\HTTP\Response|mixed
	 */
	public $response;

	public function __construct(Controller $target, &$response)
	{
		parent::__construct($target, 'respond:before');
	}
}
