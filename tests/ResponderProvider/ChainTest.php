<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\ResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ResponderProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class ChainTest extends TestCase
{
	use ProphecyTrait;

	public function test_responder_for_action(): void
	{
		$action = 'article:show';
		$responder = $this->prophesize(Responder::class)->reveal();

		$rp1 = $this->prophesize(ResponderProvider::class);
		$rp1->responder_for_action($action)->willReturn(null);

		$rp2 = $this->prophesize(ResponderProvider::class);
		$rp2->responder_for_action($action)->willReturn(null);

		$rp3 = $this->prophesize(ResponderProvider::class);
		$rp3->responder_for_action($action)->willReturn($responder);

		$rp4 = $this->prophesize(ResponderProvider::class);
		$rp4->responder_for_action($action)->shouldNotBeCalled();

		$chain = new ResponderProvider\Chain(
			$rp1->reveal(),
			$rp2->reveal(),
			$rp3->reveal(),
			$rp4->reveal(),
		);

		$this->assertSame($responder, $chain->responder_for_action($action));
	}
}
