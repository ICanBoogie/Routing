<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\Config\ControllerDefinition;

/**
 * The routing configuration: the routes, and the controllers that respond to their actions.
 *
 * It is produced by {@see ConfigBuilder}, it is readonly, and it can be exported with
 * `var_export()` to be loaded by subsequent requests without the cost of building it again.
 */
final readonly class Config
{
    /**
     * @param array{
     *     routes: RouteProvider,
     *     controllers: array<class-string, ControllerDefinition>,
     *     controller_by_action: array<non-empty-string, class-string>,
     * } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param RouteProvider $routes
     *     The routes, whether they lead to a controller or to a responder defined elsewhere.
     * @param array<class-string, ControllerDefinition> $controllers
     *     Where _key_ is the class of a controller.
     * @param array<non-empty-string, class-string> $controller_by_action
     *     Where _key_ is an action and _value_ the class of the controller that responds to it.
     */
    public function __construct(
        public RouteProvider $routes,
        public array $controllers,
        public array $controller_by_action,
    ) {
    }
}
