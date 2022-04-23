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

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteCollector;
use PHPUnit\Framework\TestCase;

final class RouteCollectorTest extends TestCase
{
    public function test_build(): void
    {
        $provider = (new RouteCollector())
            ->route('/', 'page:home')
            ->any('/photos', 'photos:list', id: 'photos:any')
            ->get('/photos', 'photos:list')
            ->post('/photos', 'photos:create')
            ->put('/photos/<nid:\d+>', 'photos:update', id: 'photos:complete_update')
            ->patch('/photos/<nid:\d+>', 'photos:update', id: 'photos:partial_update')
            ->delete('/photos/<nid:\d+>', 'photos:delete')
            ->head('/photos/<nid:\d+>', 'photos:show', id: 'photos:show_head')
            ->options('/photos', 'options')
            ->resource('articles')
            ->collect();

        $actual = [];

        foreach ($provider as $route) {
            /* @var Route $route */
            $actual[] = [ $route->pattern, $route->action, $route->methods, $route->id ];
        }

        $this->assertEquals([

            [ Pattern::from('/'), 'page:home', RequestMethod::METHOD_ANY, null ],
            [ Pattern::from('/photos'), 'photos:list', RequestMethod::METHOD_ANY, 'photos:any' ],
            [ Pattern::from('/photos'), 'photos:list', RequestMethod::METHOD_GET, null ],
            [ Pattern::from('/photos'), 'photos:create', RequestMethod::METHOD_POST, null ],
            [ Pattern::from('/photos/<nid:\d+>'), 'photos:update', RequestMethod::METHOD_PUT, 'photos:complete_update' ],
            [ Pattern::from('/photos/<nid:\d+>'), 'photos:update', RequestMethod::METHOD_PATCH, 'photos:partial_update' ],
            [ Pattern::from('/photos/<nid:\d+>'), 'photos:delete', RequestMethod::METHOD_DELETE, null ],
            [ Pattern::from('/photos/<nid:\d+>'), 'photos:show', RequestMethod::METHOD_HEAD, 'photos:show_head' ],
            [ Pattern::from('/photos'), 'options', RequestMethod::METHOD_OPTIONS, null ],
            [ Pattern::from('/articles'), 'articles:list', RequestMethod::METHOD_GET, null ],
            [ Pattern::from('/articles/new'), 'articles:new', RequestMethod::METHOD_GET, null ],
            [ Pattern::from('/articles'), 'articles:create', RequestMethod::METHOD_POST, null ],
            [ Pattern::from('/articles/<id:\d+>'), 'articles:show', RequestMethod::METHOD_GET, null ],
            [ Pattern::from('/articles/<id:\d+>/edit'), 'articles:edit', RequestMethod::METHOD_GET, null ],
            [ Pattern::from('/articles/<id:\d+>'), 'articles:update', [ RequestMethod::METHOD_PUT, RequestMethod::METHOD_PATCH ], null ],
            [ Pattern::from('/articles/<id:\d+>'), 'articles:delete', RequestMethod::METHOD_DELETE, null ],

        ], $actual);
    }
}
