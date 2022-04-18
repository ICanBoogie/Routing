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

/**
 * Tries a chain of controller providers until one provides a controller.
 */
final class Chain implements ActionResponderProvider
{
    /**
     * @var ActionResponderProvider[]
     */
    private readonly iterable $providers;

    public function __construct(ActionResponderProvider ...$providers)
    {
        $this->providers = $providers;
    }

    public function responder_for_action(string $action): ?Responder
    {
        foreach ($this->providers as $provider) {
            $responder = $provider->responder_for_action($action);

            if ($responder) {
                return $responder;
            }
        }

        return null;
    }
}
