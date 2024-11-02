<?php

namespace ICanBoogie\Routing\ActionResponderProvider;

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;

/**
 * Tries a chain of controller providers until one provides a controller.
 */
final readonly class Chain implements ActionResponderProvider
{
    /**
     * @var ActionResponderProvider[]
     */
    private iterable $providers;

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
