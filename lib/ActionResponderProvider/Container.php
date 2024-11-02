<?php

namespace ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use Psr\Container\ContainerInterface;

/**
 * Provides responders with a PSR container.
 */
final readonly class Container implements ActionResponderProvider
{
    /**
     * @param array<string, string> $aliases
     *     Aliases can be used to map multiple actions to the same service.
     */
    public function __construct(
        private ContainerInterface $container,
        private array $aliases = [],
    ) {
    }

    public function responder_for_action(string $action): ?Responder
    {
        $action = $this->aliases[$action] ?? $action;

        if (!$this->container->has($action)) {
            return null;
        }

        return $this->container->get($action); // @phpstan-ignore-line
    }
}
