<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Dispatcher;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Dispatcher;
use ICanBoogie\Routing\Route;

/**
 * Event class for the `ICanBoogie\Routing\Dispatcher::rescue` event.
 *
 * Third parties may use this event to _rescue_ an exception by providing a suitable response.
 * Third parties may also use this event to replace the exception to rethrow.
 */
class RescueEvent extends \ICanBoogie\Exception\RescueEvent
{
	/**
	 * Route to rescue.
	 *
	 * @var Route
	 */
	public $route;

	/**
	 * Initializes the {@link $route} property.
	 *
	 * @param \Exception $target
	 * @param Request $request
	 * @param Route $route
	 * @param Response|null $response
	 */
	public function __construct(\Exception &$target, Request $request, Route $route, &$response)
	{
		$this->route = $route;

		parent::__construct($target, $request, $response);
	}
}