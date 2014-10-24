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
 * Event class for the `routing.collect_routes:before` event.
 *
 * Third parties may use this event to alter the configuration fragments before they are
 * synthesized.
 */
class BeforeCollectRoutesEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the configuration fragments.
	 *
	 * @var array
	 */
	public $fragments;

	/**
	 * The event is constructed with the type `routing.collect_routes:before`.
	 *
	 * @param array $fragments Reference to the fragments to alter.
	 */
	public function __construct(&$fragments)
	{
		$this->fragments = &$fragments;

		parent::__construct(null, 'routing.collect_routes:before');
	}
}
