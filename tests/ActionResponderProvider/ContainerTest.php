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
use ICanBoogie\Routing\ActionResponderProvider\Container;
use olvlvl\Given\GivenTrait;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    use GivenTrait;

    public function test_provides_responder(): void
    {
        $responder = $this->createMock(Responder::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->will(
                $this
                    ->given($action_ok = 'article:list')->return(true)
                    ->given($action_ko = 'article:show')->return(false)
            );

        $container
            ->method('get')
            ->with($action_ok)
            ->willReturn($responder);

        $provider = new Container($container);

        $this->assertSame($responder, $provider->responder_for_action($action_ok));
        $this->assertNull($provider->responder_for_action($action_ko));
    }

    public function test_provides_responder_with_aliases(): void
    {
        $responder = $this->createMock(Responder::class);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->will(
                $this
                    ->given($idOk = 'controller.articles')->return(true)
                    ->given($idKo = 'something:else')->return(false)
            );
        $container
            ->method('get')
            ->with($idOk)
            ->willReturn($responder);

        $provider = new Container($container, [
            'articles:show' => 'controller.articles',
            'articles:list' => 'controller.articles',
        ]);

        $this->assertSame($responder, $provider->responder_for_action('articles:show'));
        $this->assertSame($responder, $provider->responder_for_action('articles:list'));
        $this->assertNull($provider->responder_for_action($idKo));
    }
}
