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
use ICanBoogie\Routing\RouteDefinition;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\RouteCollection;

class ActionTraitTest extends \PHPUnit\Framework\TestCase
{
	public function setUp()
	{
		$events = new EventCollection;

		EventCollectionProvider::define(function() use ($events) {

			return $events;

		});
	}

	public function test_action()
	{
		$routes = new RouteCollection([

			'default' => [

				RouteDefinition::PATTERN => '/blog/<year:\d{4}>-<month:\d{2}>-:slug.html',
				RouteDefinition::CONTROLLER => ActionController::class,
				RouteDefinition::ACTION => 'view'
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

	/**
	 * @dataProvider provider_resource_action
	 *
	 * @param string $action
	 */
	public function test_resource_action($action)
	{
		$rc = uniqid();

		$method = "action_$action";

		$controller = $this
			->getMockBuilder(ActionController::class)
			->disableOriginalConstructor()
			->setMethods([ 'get_action', $method ])
			->getMockForAbstractClass();
		$controller
			->expects($this->once())
			->method('get_action')
			->willReturn($action);
		$controller
			->expects($this->once())
			->method($method)
			->willReturn($rc);

		/* @var $controller ActionController */

		$this->assertSame($rc, $controller(Request::from('/')));
	}

	public function provider_resource_action()
	{
		$methods = 'index new create show edit update delete';
		$cases = [];

		foreach (explode(' ', $methods) as $method)
		{
			$cases[] = [ $method ];
		}

		return $cases;
	}
}
