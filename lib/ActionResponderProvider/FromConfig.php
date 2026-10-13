<?php

namespace ICanBoogie\Routing\ActionResponderProvider;

use Closure;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use ICanBoogie\Routing\Config;
use ICanBoogie\Routing\MethodResponder;

/**
 * Provides the responders of the controllers defined by a {@see Config}.
 *
 * There is one {@see MethodResponder} per controller, created when one of its actions is first
 * requested. The controllers are obtained through a closure, which is where services are wired:
 * the closure may share an instance, create one each time, or ask a container.
 */
final class FromConfig implements ActionResponderProvider
{
    /**
     * @var array<class-string, MethodResponder>
     */
    private array $responders = [];

    /**
     * @param Closure(class-string): object $controller_factory
     *     Provides the controller of the specified class.
     */
    public function __construct(
        private readonly Config $config,
        private readonly Closure $controller_factory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function responder_for_action(string $action): ?Responder
    {
        $class = $this->config->controller_by_action[$action] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->responders[$class] ??= new MethodResponder(
            fn(): object => ($this->controller_factory)($class),
            $this->config->controllers[$class]->actions,
        );
    }
}
