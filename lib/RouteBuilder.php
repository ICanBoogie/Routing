<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\Config\TransientRoute;
use ICanBoogie\Routing\Exception\ConfigNotValid;

/**
 * Defines the routes of a controller, and their prefixes, for people who don't use attributes.
 *
 * ```php
 * $builder->add_controller(
 *     PhotosController::class,
 *     route_builder: fn(RouteBuilder $r) => $r
 *         ->prefix(route: '/cart', action: 'cart')
 *         ->get('/photos/:id', 'show'),
 * );
 * ```
 *
 * It is the counterpart of the attributes {@see Attribute\Route} and {@see Attribute\Prefix}.
 */
final class RouteBuilder
{
    /**
     * The prefix defined with {@see prefix()}.
     */
    private(set) ?Prefix $prefix = null;

    /**
     * @var list<TransientRoute>
     */
    private(set) array $routes = [];

    /**
     * Defines the prefixes of the routes of the controller, it applies to all of them wherever the
     * call sits. See {@see Prefix}.
     *
     * @param non-empty-string|null $route
     * @param non-empty-string|null $action
     *
     * @return $this
     *
     * @throws ConfigNotValid if the prefix is already defined.
     */
    public function prefix(?string $route = null, ?string $action = null): self
    {
        if ($this->prefix) {
            throw new ConfigNotValid("The prefix is already defined.");
        }

        $this->prefix = new Prefix($route, $action);

        return $this;
    }

    /**
     * Defines a route to a method of the controller.
     *
     * @param string $pattern
     *     Pattern of the route. It's alright to have an _empty_ pattern as long as a prefix defines
     *     the base pattern.
     * @param non-empty-string $method
     *     Name of the method of the controller.
     * @param RequestMethod|RequestMethod[] $methods
     *     Request method(s) accepted by the route.
     * @param non-empty-string|null $action
     *     Identifier of a qualified action. e.g. 'articles:show'.
     *     If it is not defined, the action is inferred from the controller and the method.
     * @param non-empty-string|null $id
     *     Identifier of the route.
     *
     * @return $this
     */
    public function route(
        string $pattern,
        string $method,
        RequestMethod|array $methods = RequestMethod::METHOD_ANY,
        ?string $action = null,
        ?string $id = null,
    ): self {
        $this->routes[] = new TransientRoute($pattern, $method, $methods, $action, $id);

        return $this;
    }

    /**
     * Defines a route to a method of the controller, for the request method ANY.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function any(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_ANY, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method GET.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function get(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_GET, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method POST.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function post(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_POST, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method PUT.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function put(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_PUT, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method PATCH.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function patch(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_PATCH, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method DELETE.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function delete(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_DELETE, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method HEAD.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function head(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_HEAD, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method OPTIONS.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function options(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_OPTIONS, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method CONNECT.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function connect(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_CONNECT, $action, $id);
    }

    /**
     * Defines a route to a method of the controller, for the request method TRACE.
     *
     * @param string $pattern
     * @param non-empty-string $method
     * @param non-empty-string|null $action
     * @param non-empty-string|null $id
     *
     * @return $this
     *
     * @see route()
     */
    public function trace(string $pattern, string $method, ?string $action = null, ?string $id = null): self
    {
        return $this->route($pattern, $method, RequestMethod::METHOD_TRACE, $action, $id);
    }
}
