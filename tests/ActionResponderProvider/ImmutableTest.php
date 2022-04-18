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

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

use function uniqid;

final class ImmutableTest extends TestCase
{
    use ProphecyTrait;

    public function test_responder_for_action(): void
    {
        $stu = new ActionResponderProvider\Immutable([
            uniqid() => $this->mockResponder(),
            uniqid() => $this->mockResponder(),
            $action = uniqid() => $responder = $this->mockResponder(),
            uniqid() => $this->mockResponder(),
        ]);

        $this->assertSame($responder, $stu->responder_for_action($action));
    }

    public function test_iterable(): void
    {
        $stu = new ActionResponderProvider\Immutable([
            $a1 = uniqid() => $r1 = $this->mockResponder(),
            $a2 = uniqid() => $r2 = $this->mockResponder(),
            $a3 = uniqid() => $r3 = $this->mockResponder(),
        ]);

        $actions = [];
        $responders = [];

        foreach ($stu as $action => $responder) {
            $actions[] = $action;
            $responders[] = $responder;
        }

        $this->assertSame([ $a1, $a2, $a3 ], $actions);
        $this->assertSame([ $r1, $r2, $r3 ], $responders);
    }

    private function mockResponder(): Responder
    {
        return $this->prophesize(Responder::class)->reveal();
    }
}
