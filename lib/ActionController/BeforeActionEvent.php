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
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;
use ICanBoogie\Routing\Route;

class BeforeActionEvent extends Event
{
	/**
	 * The action performed by the controller.
	 *
	 * @var string
	 */
	public $action;

	/**
	 * Reference to the response returned by the controller.
	 *
	 * @var mixed
	 */
	public $response;

	/**
	 * The route that matched the request.
	 *
	 * @var Route
	 */
	public $route;

	/**
	 * The request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * The event is constructed with the type 'action:before'.
	 *
	 * @param Controller $target
	 * @param string $action;
	 * @param mixed $response
	 * @param Route $route
	 * @param Request $request
	 */
	public function __construct(Controller $target, $action, &$response, Route $route, Request $request)
	{
		$this->action = $action;
		$this->route = $route;
		$this->request = $request;
		$this->response = $response;

		parent::__construct($target, 'action:before');
	}
}
