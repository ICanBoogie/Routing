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

use Closure;
use ICanBoogie\HTTP\Request;

/**
 * Controller wrapper for closures.
 */
final class ClosureController extends Controller
{
	/**
	 * @var Closure
	 */
	private $closure;

	public function __construct(Closure $closure)
	{
		$this->closure = Closure::bind($closure, $this);
	}

	/**
	 * @inheritdoc
	 */
	protected function action(Request $request)
	{
		return ($this->closure)(...array_values($request->path_params));
	}
}
