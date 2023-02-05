<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\ICanBoogie\Routing\ActionResponderProvider;

use Exception;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use ICanBoogie\Routing\ActionResponderProvider\Chain;
use PHPUnit\Framework\TestCase;

final class ChainTest extends TestCase
{
    public function test_responder_for_action(): void
    {
        $action = 'article:show';
        $responder = $this->createMock(Responder::class);

        $rp1 = $this->createMock(ActionResponderProvider::class);
        $rp1->method('responder_for_action')->with($action)->willReturn(null);

        $rp2 = $this->createMock(ActionResponderProvider::class);
        $rp2->method('responder_for_action')->with($action)->willReturn(null);

        $rp3 = $this->createMock(ActionResponderProvider::class);
        $rp3->method('responder_for_action')->with($action)->willReturn($responder);

        $rp4 = $this->createMock(ActionResponderProvider::class);
        $rp4->expects($this->never())->method('responder_for_action')->with($action)->willThrowException(new Exception("madonna"));

        $chain = new Chain(
            $rp1,
            $rp2,
            $rp3,
            $rp4,
        );

        $this->assertSame($responder, $chain->responder_for_action($action));
    }
}
