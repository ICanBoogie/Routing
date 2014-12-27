<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ActionController;

use ICanBoogie\Event;
use ICanBoogie\Routing\ActionController;

/**
 * Event class for the `ICanBoogie\Routing\ActionController::action` event.
 *
 * Event hooks may use this event to alter the response of the action.
 *
 * @package ICanBoogie\Routing\ActionController
 */
class ActionEvent extends Event
{
	/**
	 * Reference to the response returned by the controller.
	 *
	 * @var mixed
	 */
	public $response;

	/**
	 * The event is constructed with the type 'action:before'.
	 *
	 * @param ActionController $target
	 * @param mixed $response
	 */
	public function __construct(ActionController $target, &$response)
	{
		$this->response = &$response;

		parent::__construct($target, 'action');
	}
}
