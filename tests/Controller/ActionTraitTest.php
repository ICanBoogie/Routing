<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\Controller;

use Closure;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller\ActionTrait;
use ICanBoogie\Routing\Route;
use LogicException;
use PHPUnit\Framework\TestCase;

use function uniqid;

class ActionTraitTest extends TestCase
{
	public function test_resolve_action_args(): void
	{
		$stu = new class () {
			use ActionTrait {
				resolve_action_args as public;
			}
		};

		$path_params = [ uniqid() ];

		$this->assertSame(
			$path_params,
			$stu->resolve_action_args(Request::from([ Request::OPTION_PATH_PARAMS => $path_params ]))
		);
	}

	public function test_resolve_action_method_no_method(): void
	{
		$stu = new class () {
			use ActionTrait {
				resolve_action_method as public;
			}
		};

		$request = Request::from();
		$request->context->add(new Route('/', 'articles:show'));

		$this->expectException(LogicException::class);
		$this->expectExceptionMessageMatches("/Unable to find action method, tried: get_articles_show/");

		$stu->resolve_action_method($request);
	}

	/**
	 * @dataProvider provide_resolve_action_method
	 */
	public function test_resolve_action_method(string $expected, object $stu): void
	{
		$request = Request::from();
		$request->context->add(new Route('/', 'articles:show'));

		$this->assertSame(
			$expected,
			$stu->resolve_action_method($request) // @phpstan-ignore-line
		);
	}

	/**
	 * @return mixed[]
	 */
	public function provide_resolve_action_method(): array
	{
		return [

			[
				'get_articles_show',
				new class () {
					/**
					 * @uses get_articles_show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function get_articles_show()
					{
					}
				}
			],

			[
				'any_articles_show',
				new class () {
					/**
					 * @uses any_articles_show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function any_articles_show()
					{
					}
				}
			],

			[
				'articles_show',
				new class () {
					/**
					 * @uses articles_show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function articles_show()
					{
					}
				}
			],

			[
				'get_show',
				new class () {
					/**
					 * @uses get_show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function get_show()
					{
					}
				}
			],

			[
				'any_show',
				new class () {
					/**
					 * @uses any_show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function any_show()
					{
					}
				}
			],

			[
				'show',
				new class () {
					/**
					 * @uses show
					 */
					use ActionTrait {
						resolve_action_method as public;
					}

					// @phpstan-ignore-next-line
					private function show()
					{
					}
				}
			],

		];
	}

	public function test_resolve_action_and_action(): void
	{
		$path_params = [ uniqid() ];

		$assert = function (Request $r, string $nid) use ($path_params) {
			$this->assertSame($path_params, [ $nid ]);
		};

		$stu = new class ($assert, $response = uniqid()) {
			/**
			 * @uses show
			 */
			use ActionTrait {
				action as public;
				resolve_action as public;
			}

			public function __construct(
				private readonly Closure $assert,
				private readonly string $response
			) {
			}

			// @phpstan-ignore-next-line
			private function show(Request $request, string $nid): string
			{
				($this->assert)($request, $nid);

				return $this->response;
			}
		};

		$request = Request::from([ Request::OPTION_PATH_PARAMS => $path_params ]);
		$request->context->add(new Route('/', 'articles:show'));

		$action = $stu->resolve_action($request);

		$this->assertSame($response, $action());
		$this->assertSame($response, $stu->action($request));
	}
}
