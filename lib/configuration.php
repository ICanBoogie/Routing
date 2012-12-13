<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routes;

class Configurer
{
	public function __invoke(array $fragments)
	{
		/* note: should be moved to an event hook */

		global $core;

		$module_roots = array();

		foreach ($core->modules->descriptors as $module_id => $descriptor)
		{
			$module_roots[$descriptor[Module::T_PATH]] = $module_id;
		}

		foreach ($fragments as $module_root => &$fragment)
		{
			$module_id = isset($module_roots[$module_root]) ? $module_roots[$module_root] : null;

			foreach ($fragment as $route_id => &$route)
			{
				$route += array
				(
					'via' => Request::METHOD_ANY,
					'module' => $module_id
				);
			}
		}

		unset($fragment);
		unset($route);

		/* /note */

		new Configurer\BeforeCollectEvent($collection, array('fragments' => &$fragments));

		$routes = array();

		foreach ($fragments as $path => $fragment)
		{
			foreach ($fragment as $id => $route)
			{
				$routes[$id] = $route + array
				(
					'pattern' => null
				);
			}
		}

		new Configurer\CollectEvent($collection, array('routes' => &$routes));

		return $routes;
	}
}

namespace ICanBoogie\Routes\Configurer;

/**
 * Event class for the `ICanBoogie\Events::collect:before` event.
 *
 * Third parties may use this event to alter the configuration fragments before they are
 * synthesized.
 */
class BeforeCollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the configuration fragments.
	 *
	 * @var array
	 */
	public $fragments;

	/**
	 * The event is constructed with the type `alter:before`.
	 *
	 * @param \ICanBoogie\Routes $target The routes collection.
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Routes\Configurer $target, array $payload)
	{
		parent::__construct($target, 'collect:before', $payload);
	}
}

/**
 * Event class for the `ICanBoogie\Events::collect` event.
 *
 * Third parties may use this event to alter the routes read from the configuration.
 */
class CollectEvent extends \ICanBoogie\Event
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
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Routes\Configurer $target, array $payload)
	{
		parent::__construct($target, 'collect', $payload);
	}
}