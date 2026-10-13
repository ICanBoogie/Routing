<?php

namespace ICanBoogie\Routing\Config;

/**
 * Defines the actions of a controller.
 */
final readonly class ControllerDefinition
{
    /**
     * @param array{ actions: array<non-empty-string, ActionDefinition> } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param array<non-empty-string, ActionDefinition> $actions
     *     Where _key_ is an action, and _value_ how to invoke the method of the controller.
     */
    public function __construct(
        public array $actions,
    ) {
    }
}
