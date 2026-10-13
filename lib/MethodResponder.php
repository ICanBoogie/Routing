<?php

namespace ICanBoogie\Routing;

use Closure;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Config\ActionDefinition;
use LogicException;
use TypeError;

use function get_debug_type;
use function sprintf;

/**
 * Responds to the actions of a controller by calling its methods.
 *
 * The controller is a plain object. The method to call is found with the action of the route
 * that matched the request, which is available in the request's context. The arguments are
 * produced by the parameters of the method's plan, and the result is the response, or an object
 * that turns itself into one, see {@see IntoResponse}.
 *
 * The controller is obtained for each request through a closure, whether the controller is shared
 * or created for the occasion is up to the closure.
 */
final readonly class MethodResponder implements Responder
{
    /**
     * @param Closure(): object $controller
     *     Provides the controller.
     * @param array<string, ActionDefinition> $actions
     *     Where _key_ is an action, and _value_ how to invoke the method of the controller.
     */
    public function __construct(
        private Closure $controller,
        private array $actions,
    ) {
    }

    /**
     * @throws LogicException if the responder doesn't handle the action of the matched route.
     * @throws TypeError if the method returns something other than a response or an
     *     {@see IntoResponse}.
     */
    public function respond(Request $request): Response
    {
        $action = $request->context->get(Route::class)->action;
        $definition = $this->actions[$action]
            ?? throw new LogicException("The responder doesn't handle the action '$action'.");

        $args = [];

        foreach ($definition->plan as $parameter) {
            $args[] = $parameter->resolve($request);
        }

        $controller = ($this->controller)();
        $result = $controller->{$definition->method}(...$args);

        return match (true) {
            $result instanceof Response => $result,
            $result instanceof IntoResponse => $result->into_response($request),
            default => throw new TypeError(
                sprintf(
                    "%s::%s() must return %s or %s, %s returned.",
                    $controller::class,
                    $definition->method,
                    Response::class,
                    IntoResponse::class,
                    get_debug_type($result),
                )
            ),
        };
    }
}
