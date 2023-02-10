<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\RouteMaker\Options;

/**
 * Collect routes to build a {@link RouteProvider}.
 */
class RouteCollector
{
    private RouteProvider\Mutable $routes;

    public function __construct()
    {
        $this->routes = new RouteProvider\Mutable();
    }

    public function collect(): RouteProvider
    {
        return new RouteProvider\Immutable($this->routes);
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     * @param RequestMethod|RequestMethod[] $methods Request method(s) accepted by the route.
     *
     * @return $this
     */
    public function route(
        string $pattern,
        string $action,
        RequestMethod|array $methods = RequestMethod::METHOD_ANY,
        string|null $id = null
    ): self {
        $this->routes->add_routes(new Route($pattern, $action, $methods, $id));

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     *
     * @return $this
     */
    public function any(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_ANY, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     *
     * @return $this
     */
    public function get(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_GET, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:create'.
     *
     * @return $this
     */
    public function post(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_POST, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:update'.
     *
     * @return $this
     */
    public function put(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_PUT, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:update'.
     *
     * @return $this
     */
    public function patch(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_PATCH, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:delete'.
     *
     * @return $this
     */
    public function delete(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_DELETE, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     *
     * @return $this
     */
    public function head(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_HEAD, $id);

        return $this;
    }

    /**
     * Add a route.
     *
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     *
     * @return $this
     */
    public function options(string $pattern, string $action, string|null $id = null): self
    {
        $this->route($pattern, $action, RequestMethod::METHOD_OPTIONS, $id);

        return $this;
    }

    /**
     * Adds resource routes.
     *
     * **Note:** The respond definitions for the resource are created by {@link RouteMaker::resource}. Both methods
     * accept the same arguments.
     *
     * @see RouteMaker::resource
     */
    public function resource(string $name, Options $options = null): self
    {
        $this->routes->add_routes(...RouteMaker::resource($name, $options));

        return $this;
    }
}
