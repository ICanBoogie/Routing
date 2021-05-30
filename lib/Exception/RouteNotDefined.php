<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Exception;

use ICanBoogie\Accessor\AccessorTrait;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Routing\Exception;
use Throwable;

/**
 * Exception thrown when a route does not exists.
 *
 * @property-read string $id The identifier of the route.
 */
class RouteNotDefined extends \Exception implements Exception
{
	/**
	 * @uses get_id
	 */
	use AccessorTrait;

	/**
	 * @var string
	 */
	private $id;

	private function get_id(): string
	{
		return $this->id;
	}

	public function __construct(string $id, int $code = Status::NOT_FOUND, Throwable $previous = null)
	{
		$this->id = $id;

		parent::__construct($this->format_message($id), $code, $previous);
	}

	private function format_message(string $id): string
	{
		return "The route `$id` is not defined.";
	}
}
