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

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Controller\ActionTraitTest\ActionController;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\RouteCollection;

class ActionTraitTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
		$events = new EventCollection;

		EventCollectionProvider::using(function() use ($events) {

			return $events;

		});
	}

	public function test_action()
	{
		$routes = new RouteCollection([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => ActionController::class . '#view'
			]

		]);

		$dispatcher = new RouteDispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$request->test = $this;
		$response = $dispatcher($request);
		$this->assertInstanceOf(Response::class, $response);
		$this->assertTrue($response->status->is_successful);
		$this->assertEquals('HERE', $response->body);
	}

	/**
	 * @expectedException \ICanBoogie\Routing\ActionNotDefined
	 */
	public function test_should_throw_exception_when_action_is_not_defined()
	{
		$routes = new RouteCollection([

			'default' => [

				'pattern' => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				'controller' => ActionController::class
			]

		]);

		$dispatcher = new RouteDispatcher($routes);
		$request = Request::from("/blog/2014-12-my-awesome-post.html");
		$request->test = $this;
		$dispatcher($request);
	}

	public function test_method_action()
	{
		$rc = uniqid();
		$action = 'action' . uniqid();

		$controller = $this
			->getMockBuilder(ActionController::class)
			->disableOriginalConstructor()
			->setMethods([ 'get_action', 'action_post_' . $action ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('get_action')
			->willReturn($action);
		$controller
			->expects($this->once())
			->method('action_post_' . $action)
			->willReturn($rc);

		/* @var $controller ActionController */

		$this->assertSame($rc, $controller(Request::from([ 'uri' => '/', 'is_post' => true ])));
	}

	public function test_any_action()
	{
		$rc = uniqid();
		$action = 'action' . uniqid();

		$controller = $this
			->getMockBuilder(ActionController::class)
			->disableOriginalConstructor()
			->setMethods([ 'get_action', 'action_any_' . $action ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('get_action')
			->willReturn($action);
		$controller
			->expects($this->once())
			->method('action_any_' . $action)
			->willReturn($rc);

		/* @var $controller ActionController */

		$this->assertSame($rc, $controller(Request::from([ 'uri' => '/', 'is_post' => true ])));
	}
}
