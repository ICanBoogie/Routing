<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing;

use ICanBoogie\EventCollection;
use ICanBoogie\EventCollectionProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Route;
use PHPUnit\Framework\TestCase;

final class ControllerAbstractTest extends TestCase
{
    /** @phpstan-ignore-next-line  */
    private readonly Route $route;
    /** @phpstan-ignore-next-line  */
    private readonly Request $request;

    protected function setUp(): void
    {
        $events = new EventCollection();

        EventCollectionProvider::define(fn(): EventCollection => $events);

        /** @phpstan-ignore-next-line  */
        $this->route = new Route('/', 'articles:show');
        /** @phpstan-ignore-next-line  */
        $this->request = Request::from();
        $this->request->context->add($this->route);
    }

    public function test_get_route(): void
    {
        $controller = new class extends ControllerAbstract {
            protected function action(Request $request): mixed
            {
                return $this->route->action;
            }
        };


        $response = $controller->respond($this->request);

        $this->assertSame('articles:show', $response->body);
        $this->assertSame($this->route, $controller->route);
    }

    public function test_builtin_response(): void
    {
        $controller = new class extends ControllerAbstract {
            protected function action(Request $request): mixed
            {
                $this->response->body = "Hello!";

                return null;
            }
        };

        $response = $controller->respond($this->request);

        $this->assertEquals("Hello!", $response->body);
    }

    public function test_new_response(): void
    {
        $response = new Response();
        $controller = new class ($response) extends ControllerAbstract {
            public function __construct(
                private readonly Response $_response
            ) {
            }

            protected function action(Request $request): Response
            {
                return $this->_response;
            }
        };

        $actual = $controller->respond($this->request);

        $this->assertSame($response, $actual);
    }
}
