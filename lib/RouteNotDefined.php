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
 * Exception thrown when a route does not exists.
 *
 * @property-read string $id The identifier of the route.
 */
class RouteNotDefined extends \Exception implements Exception
{
	use \ICanBoogie\GetterTrait;

	private $id;

	protected function get_id()
	{
		return $this->id;
	}

	/**
	 * @param string $id Identifier of the route.
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct($id, $code=404, \Exception $previous=null)
	{
		$this->id = $id;

		parent::__construct("The route <q>$id</q> is not defined.", $code, $previous);
	}
}
