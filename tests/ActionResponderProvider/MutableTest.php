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
use Test\ICanBoogie\Routing\FakeResponder;

use function uniqid;

final class MutableTest extends TestCase
{
    public function test_responder_for_action(): void
    {
        $stu = new ActionResponderProvider\Mutable();
        $stu->add_responder(uniqid(), $this->makeResponder());
        $stu->add_responder(uniqid(), $this->makeResponder());
        $stu->add_responder($action = uniqid(), $responder = $this->makeResponder());
        $stu->add_responder(uniqid(), $this->makeResponder());

        $this->assertSame($responder, $stu->responder_for_action($action));
    }

    public function test_iterable(): void
    {
        $stu = new ActionResponderProvider\Mutable();
        $stu->add_responder($a1 = uniqid(), $r1 = $this->makeResponder());
        $stu->add_responder($a2 = uniqid(), $r2 = $this->makeResponder());
        $stu->add_responder($a3 = uniqid(), $r3 = $this->makeResponder());

        $actions = [];
        $responders = [];

        foreach ($stu as $action => $responder) {
            $actions[] = $action;
            $responders[] = $responder;
        }

        $this->assertSame([ $a1, $a2, $a3 ], $actions);
        $this->assertSame([ $r1, $r2, $r3 ], $responders);
    }

    private function makeResponder(): Responder
    {
        return new FakeResponder();
    }
}
