<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
	use ProphecyTrait;

	public function test_provides_responder(): void
	{
		$responder = $this->prophesize(Responder::class)->reveal();

		$container = $this->prophesize(ContainerInterface::class);
		$container->has($action_ok = 'article:index')
			->willReturn(true);
		$container->has($action_ko = 'article:show')
			->willReturn(false);
		$container->get($action_ok)
			->willReturn($responder);

		$provider = new Container($container->reveal());

		$this->assertSame($responder, $provider->responder_for_action($action_ok));
		$this->assertNull($provider->responder_for_action($action_ko));
	}
}
