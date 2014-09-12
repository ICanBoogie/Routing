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
 * Event class for the `routing.collect_routes` event.
 *
 * Third parties may use this event to alter the routes read from the configuration.
 */
class CollectRoutesEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the routes.
	 *
	 * @var array[string]array
	 */
	public $routes;

	/**
	 * The event is constructed with the type `collect`.
	 *
	 * @param \ICanboogie\Routes $target The routes collection.
	 */
	public function __construct(array &$routes)
	{
		$this->routes = &$routes;

		parent::__construct(null, 'collect_routes');
	}
}