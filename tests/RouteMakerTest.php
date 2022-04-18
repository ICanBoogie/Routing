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
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteMaker as Make;
use ICanBoogie\Routing\RouteMaker\Basics;
use ICanBoogie\Routing\RouteMaker\Options;
use PHPUnit\Framework\TestCase;

final class RouteMakerTest extends TestCase
{
    /**
     * @dataProvider provide_actions_options
     *
     * @param array<string, Basics> $basics
     * @param Route[] $expected
     */
    public function test_actions(array $basics, ?Options $options, array $expected): void
    {
        $this->assertEquals($expected, Make::actions('dogs', $basics, $options));
    }

    /**
     * @return mixed[]
     */
    public function provide_actions_options(): array
    {
        return [

            [
                [
                    'walk' => new Basics(
                        '/{name}/{id}/walk',
                        RequestMethod::METHOD_CONNECT
                    ),
                    'run' => new Basics(
                        '/run/{name}/{id}',
                        [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ]
                    ),
                ],
                null,
                [
                    new Route(
                        '/dogs/<id:\d+>/walk',
                        'dogs:walk',
                        RequestMethod::METHOD_CONNECT,
                        id: 'dogs:walk'
                    ),
                    new Route(
                        '/run/dogs/<id:\d+>',
                        'dogs:run',
                        [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ],
                        id: 'dogs:run'
                    ),
                ],
            ],

            [
                [
                    'walk' => new Basics(
                        '/{name}/{id}/walk',
                        RequestMethod::METHOD_CONNECT
                    ),
                    'run' => new Basics(
                        '/run/{name}/{id}',
                        [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ]
                    ),
                ],
                new Options(only: [ 'walk' ]),
                [
                    new Route(
                        '/dogs/<id:\d+>/walk',
                        'dogs:walk',
                        RequestMethod::METHOD_CONNECT,
                        id: 'dogs:walk'
                    ),
                ],
            ],

        ];
    }

    /**
     * @dataProvider provide_resource_options
     *
     * @param Route[] $expected
     */
    public function test_resource(?Options $options, array $expected): void
    {
        $this->assertEquals($expected, Make::resource('photos', $options));
    }

    /**
     * @return mixed[]
     */
    public function provide_resource_options(): array
    {
        return [

            [
                new Options(only: [ Make::ACTION_LIST ]),
                [
                    new Route('/photos', 'photos:list', RequestMethod::METHOD_GET, id: 'photos:list'),
                ],
            ],

            [
                new Options(only: [ Make::ACTION_LIST, Make::ACTION_SHOW ], ids: [ Make::ACTION_LIST => 'my-list' ]),
                [
                    new Route('/photos', 'photos:list', RequestMethod::METHOD_GET, id: 'my-list'),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                ],
            ],

            [
                new Options(only: [ Make::ACTION_LIST, Make::ACTION_SHOW ]),
                [
                    new Route('/photos', 'photos:list', RequestMethod::METHOD_GET, id: 'photos:list'),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                ],
            ],

            [
                new Options(except: [ Make::ACTION_DELETE ]),
                [
                    new Route('/photos', 'photos:list', RequestMethod::METHOD_GET, id: 'photos:list'),
                    new Route('/photos/new', 'photos:new', RequestMethod::METHOD_GET, id: 'photos:new'),
                    new Route('/photos', 'photos:create', RequestMethod::METHOD_POST, id: 'photos:create'),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                    new Route('/photos/<id:\d+>/edit', 'photos:edit', RequestMethod::METHOD_GET, 'photos:edit'),
                    new Route(
                        '/photos/<id:\d+>',
                        'photos:update',
                        [ RequestMethod::METHOD_PUT, RequestMethod::METHOD_PATCH ],
                        id: 'photos:update'
                    ),
                ],
            ],

            [
                new Options(except: [ Make::ACTION_CREATE, Make::ACTION_UPDATE, Make::ACTION_DELETE ]),
                [
                    new Route('/photos', 'photos:list', RequestMethod::METHOD_GET, id: 'photos:list'),
                    new Route('/photos/new', 'photos:new', RequestMethod::METHOD_GET, id: 'photos:new'),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                    new Route('/photos/<id:\d+>/edit', 'photos:edit', RequestMethod::METHOD_GET, id: 'photos:edit'),
                ],
            ],

            [
                new Options(only: [ Make::ACTION_CREATE, Make::ACTION_SHOW ], as: [ Make::ACTION_CREATE => 'madonna' ]),
                [
                    new Route('/photos', 'madonna', RequestMethod::METHOD_POST, id: 'madonna'),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                ],
            ],

            [
                new Options(only: [ Make::ACTION_CREATE, Make::ACTION_SHOW ], basics: [
                    Make::ACTION_CREATE => new Basics(
                        '/prefix/{name}/suffix',
                        RequestMethod::METHOD_PATCH
                    )
                ]),
                [
                    new Route(
                        '/prefix/photos/suffix',
                        'photos:create',
                        RequestMethod::METHOD_PATCH,
                        id: 'photos:create'
                    ),
                    new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET, id: 'photos:show'),
                ],
            ],

        ];
    }
}
