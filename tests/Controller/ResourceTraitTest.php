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
use ICanBoogie\Routing\Controller\ResourceTraitTest\ResourceController;

class ResourceTraitTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $events = new EventCollection;

        EventCollectionProvider::using(function() use ($events) {

            return $events;

        });
    }

    /**
     * @dataProvider provider_test_action
     *
     * @param string $action
     */
    public function test_action($action)
    {
        $rc = uniqid();

        $controller = $this
            ->getMockBuilder(ResourceController::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'get_action', $action ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('get_action')
            ->willReturn($action);
        $controller
            ->expects($this->once())
            ->method($action)
            ->willReturn($rc);

        /* @var $controller ResourceController */

        $this->assertSame($rc, $controller(Request::from('/')));
    }

    public function provider_test_action()
    {
        $methods = 'index create store show edit update destroy';
        $cases = [];

        foreach (explode(' ', $methods) as $method)
        {
            $cases[] = [ $method ];
        }

        return $cases;
    }

    public function test_non_resource_action()
    {
        $rc = uniqid();
        $action = 'myaction' . uniqid();

        $controller = $this
            ->getMockBuilder(ResourceController::class)
            ->disableOriginalConstructor()
            ->setMethods([ 'get_action', 'action_' . $action ])
            ->getMockForAbstractClass();
        $controller
            ->expects($this->once())
            ->method('get_action')
            ->willReturn($action);
        $controller
            ->expects($this->once())
            ->method('action_' . $action)
            ->willReturn($rc);

        /* @var $controller ResourceController */

        $this->assertSame($rc, $controller(Request::from('/')));
    }
}
