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

use ICanBoogie\HTTP\RequestMethod;
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
					'walk' => new Basics('/{name}/{id}/walk', RequestMethod::METHOD_CONNECT),
					'run' => new Basics('/run/{name}/{id}', [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ]),
				],
				null,
				[
					new Route('/dogs/<id:\d+>/walk', 'dogs:walk', RequestMethod::METHOD_CONNECT),
					new Route('/run/dogs/<id:\d+>', 'dogs:run', [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ]),
				],
			],

			[
				[
					'walk' => new Basics('/{name}/{id}/walk', RequestMethod::METHOD_CONNECT),
					'run' => new Basics('/run/{name}/{id}', [ RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET ]),
				],
				new Options(only: [ 'walk' ]),
				[
					new Route('/dogs/<id:\d+>/walk', 'dogs:walk', RequestMethod::METHOD_CONNECT),
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
				new Options(only: [ Make::ACTION_INDEX ]),
				[
					new Route('/photos', 'photos:index', RequestMethod::METHOD_GET),
				],
			],

			[
				new Options(only: [ Make::ACTION_INDEX, Make::ACTION_SHOW ], ids: [ Make::ACTION_INDEX => 'my-index' ]),
				[
					new Route('/photos', 'photos:index', RequestMethod::METHOD_GET, id: 'my-index'),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
				],
			],

			[
				new Options(only: [ Make::ACTION_INDEX, Make::ACTION_SHOW ]),
				[
					new Route('/photos', 'photos:index', RequestMethod::METHOD_GET),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
				],
			],

			[
				new Options(except: [ Make::ACTION_DELETE ]),
				[
					new Route('/photos', 'photos:index', RequestMethod::METHOD_GET),
					new Route('/photos/new', 'photos:new', RequestMethod::METHOD_GET),
					new Route('/photos', 'photos:create', RequestMethod::METHOD_POST),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
					new Route('/photos/<id:\d+>/edit', 'photos:edit', RequestMethod::METHOD_GET),
					new Route('/photos/<id:\d+>', 'photos:update', [ RequestMethod::METHOD_PUT, RequestMethod::METHOD_PATCH ]),
				],
			],

			[
				new Options(except: [ Make::ACTION_CREATE, Make::ACTION_UPDATE, Make::ACTION_DELETE ]),
				[
					new Route('/photos', 'photos:index', RequestMethod::METHOD_GET),
					new Route('/photos/new', 'photos:new', RequestMethod::METHOD_GET),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
					new Route('/photos/<id:\d+>/edit', 'photos:edit', RequestMethod::METHOD_GET),
				],
			],

			[
				new Options(only: [ Make::ACTION_CREATE, Make::ACTION_SHOW ], as: [ Make::ACTION_CREATE => 'madonna' ]),
				[
					new Route('/photos', 'madonna', RequestMethod::METHOD_POST),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
				],
			],

			[
				new Options(only: [ Make::ACTION_CREATE, Make::ACTION_SHOW ], basics: [ Make::ACTION_CREATE => new Basics(
					'/prefix/{name}/suffix', RequestMethod::METHOD_PATCH) ]),
				[
					new Route('/prefix/photos/suffix', 'photos:create', RequestMethod::METHOD_PATCH),
					new Route('/photos/<id:\d+>', 'photos:show', RequestMethod::METHOD_GET),
				],
			],

		];
	}
}
