<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use Psr\Container\ContainerInterface;

/**
 * Provides responders from a PSR container.
 */
final class Container implements ActionResponderProvider
{
    /**
     * @param array<string, string> $aliases
     *     Aliases can be used to map multiple actions to the same service.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $aliases = [],
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
