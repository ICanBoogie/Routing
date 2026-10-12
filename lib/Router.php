<?php

namespace ICanBoogie\Routing;

use Closure;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Responder\DelegateToClosure;
use ICanBoogie\HTTP\Response;

/**
 * Configures routes and controller providers at runtime.
 */
class Router
{
    private static int $anonymous_action_count = 0;

    private static function generate_anonymous_action(): string
    {
        return '__anonymous_action_' . ++self::$anonymous_action_count;
    }

    public function __construct(
        private readonly MutableRouteProvider $routes,
        private readonly MutableActionResponderProvider $responders,
    ) {
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function any(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_ANY, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function connect(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_CONNECT, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function delete(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_DELETE, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function get(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_GET, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function head(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_HEAD, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function options(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_OPTIONS, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function patch(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_PATCH, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function post(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_POST, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function put(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_PUT, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    public function trace(string $pattern, Closure $closure, string|null $action = null): self
    {
        return $this->method(RequestMethod::METHOD_TRACE, $pattern, $closure, $action);
    }

    /**
     * @param Closure(Request):Response $closure
     * @param string|null $action An action name, which takes precedence over a generated one.
     *
     * @return $this
     */
    private function method(
        RequestMethod $method,
        string $pattern,
        Closure $closure,
        string|null $action,
    ): self {
        $action ??= self::generate_anonymous_action();

        $this->routes->add_routes(new Route($pattern, $action, $method));
        $this->responders->add_responder($action, new DelegateToClosure($closure));

        return $this;
    }
}
