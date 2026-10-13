<?php

namespace Test\ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    public function test_provides_responder(): void
    {
        $responder = $this->createStub(Responder::class);

        $action_ok = 'article:list';
        $action_ko = 'article:show';

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnCallback(fn(string $id): bool => $id === $action_ok);

        $container
            ->expects($this->once())
            ->method('get')
            ->with($action_ok)
            ->willReturn($responder);

        $provider = new Container($container);

        $this->assertSame($responder, $provider->responder_for_action($action_ok));
        $this->assertNull($provider->responder_for_action($action_ko));
    }

    public function test_provides_responder_with_aliases(): void
    {
        $responder = $this->createStub(Responder::class);

        $idOk = 'controller.articles';
        $idKo = 'something:else';

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->willReturnCallback(fn(string $id): bool => $id === $idOk);
        $container
            ->expects($this->exactly(2))
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
