<?php

namespace ICanBoogie\Routing\Config;

use ICanBoogie\Routing\Parameter;

/**
 * Defines how to invoke an action: the method to call on the controller, and the plan of its
 * parameters.
 */
final readonly class ActionDefinition
{
    /**
     * @param array{
     *     method: non-empty-string,
     *     plan: list<Parameter>,
     * } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param non-empty-string $method
     *     Name of the public method of the controller.
     * @param list<Parameter> $plan
     *     The parameters of the method, in order. Each one produces its value from the request.
     */
    public function __construct(
        public string $method,
        public array $plan = [],
    ) {
    }
}
