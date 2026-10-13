<?php

namespace ICanBoogie\Routing;

use Closure;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\Config\ControllerDefinition;
use ICanBoogie\Routing\Config\TransientRoute;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ReflectionAttribute;
use ReflectionClass;

use function array_map;
use function class_exists;
use function count;

/**
 * Builds the routing {@see Config}.
 *
 * Controllers are plain classes, they are added with {@see add_responder()}, which reads the
 * attributes of the class and its methods, and optionally a {@see RouteBuilder}. Everything is
 * validated and resolved right away, so that mistakes are reported when the configuration is built,
 * not when requests are handled.
 *
 * Routes exist because a controller defines them: there is no way to add a route without the
 * action that answers it.
 *
 * ```php
 * $config = new ConfigBuilder()
 *     ->add_responder(PhotosController::class)
 *     ->add_responder(ContactController::class, fn(RouteBuilder $r) => $r
 *         ->get('/contact.html', 'new')
 *         ->post('/contact.html', 'create'))
 *     ->build();
 * ```
 */
final class ConfigBuilder
{
    private RouteProvider\Mutable $routes;

    /**
     * @var array<class-string, ControllerDefinition>
     */
    private array $controllers = [];

    /**
     * @var array<non-empty-string, class-string>
     */
    private array $controller_by_action = [];

    public function __construct()
    {
        $this->routes = new RouteProvider\Mutable();
    }

    /**
     * Adds a controller.
     *
     * The routes of the controller are defined by the attributes {@see Attribute\Route} of its
     * methods, and by the optional route builder. The prefixes shared by the routes are defined by
     * the attribute {@see Attribute\Prefix} of the class or by {@see RouteBuilder::prefix()}, not
     * both.
     *
     * The action of a route that doesn't specify one is inferred, see {@see ActionResolver}.
     *
     * @param class-string $controller
     * @param (Closure(RouteBuilder): mixed)|null $route_builder
     *     Defines the routes of the controller without attributes.
     *
     * @return $this
     *
     * @throws ConfigNotValid
     */
    public function add_responder(string $controller, ?Closure $route_builder = null): self
    {
        $class = $this->reflect($controller);
        $builder = new RouteBuilder();

        if ($route_builder) {
            $route_builder($builder);
        }

        $prefix = $this->resolve_prefix($class, $builder);
        $routes_by_method = $this->collect_routes($class, $builder);

        if (!$routes_by_method) {
            throw new ConfigNotValid("`$class->name` has no routes.");
        }

        $routes = [];
        $actions = [];

        // Nothing is registered before everything is valid.

        foreach ($routes_by_method as $method_name => $method_routes) {
            $resolved = $this->resolve_routes($class, $method_name, $method_routes, $prefix);
            $plan = PlanBuilder::build(
                $class->getMethod($method_name),
                array_map(fn(Route $route): Pattern => $route->pattern, $resolved)
            );

            foreach ($resolved as $route) {
                $this->assert_action_available($class, $route->action, $method_name, $actions);

                $actions[$route->action] = new ActionDefinition($method_name, $plan);
                $routes[] = $route;
            }
        }

        $this->routes->add_routes(...$routes);

        $this->controllers[$class->name] = new ControllerDefinition($actions);

        foreach ($actions as $action => $_) {
            $this->controller_by_action[$action] = $class->name;
        }

        return $this;
    }

    /**
     * Builds the configuration.
     *
     * The routes are compiled, which is meant for production. The configuration is cached as a PHP
     * file, so the work is done once, when the configuration is built, and not by every request.
     */
    public function build(): Config
    {
        return new Config(
            new RouteProvider\Compiled($this->routes),
            $this->controllers,
            $this->controller_by_action,
        );
    }

    /**
     * @param class-string $controller
     *
     * @return ReflectionClass<object>
     *
     * @throws ConfigNotValid
     */
    private function reflect(string $controller): ReflectionClass
    {
        if (!class_exists($controller)) {
            throw new ConfigNotValid("`$controller` is not a class.");
        }

        $class = new ReflectionClass($controller);

        if ($class->isAbstract() || $class->isEnum()) {
            throw new ConfigNotValid("`$controller` must be a concrete class.");
        }

        if (isset($this->controllers[$class->name])) {
            throw new ConfigNotValid("`$controller` is already added.");
        }

        return $class;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @throws ConfigNotValid if the prefix is defined by both the attribute and the route builder,
     *     or if the class has multiple prefixes.
     */
    private function resolve_prefix(ReflectionClass $class, RouteBuilder $builder): ?Prefix
    {
        $attributes = $class->getAttributes(Prefix::class, ReflectionAttribute::IS_INSTANCEOF);

        if (count($attributes) > 1) {
            throw new ConfigNotValid("`$class->name` has multiple prefixes, only one is allowed.");
        }

        if (!$attributes) {
            return $builder->prefix;
        }

        if ($builder->prefix) {
            throw new ConfigNotValid(
                "`$class->name` defines its prefix with both the attribute #[Prefix] and RouteBuilder::prefix()."
            );
        }

        /** @var Prefix */
        return $attributes[0]->newInstance();
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array<non-empty-string, non-empty-list<TransientRoute>>
     *     Where _key_ is the name of a method, and _value_ its routes.
     *
     * @throws ConfigNotValid if the route builder refers to a method that doesn't exist.
     */
    private function collect_routes(ReflectionClass $class, RouteBuilder $builder): array
    {
        $routes_by_method = [];

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes(Attribute\Route::class, ReflectionAttribute::IS_INSTANCEOF) as $a) {
                /** @var Attribute\Route $route */
                $route = $a->newInstance();
                $routes_by_method[$method->name][] = new TransientRoute(
                    $route->pattern,
                    $method->name,
                    $route->methods,
                    $route->action,
                    $route->id,
                );
            }
        }

        foreach ($builder->routes as $route) {
            if (!$class->hasMethod($route->method)) {
                throw new ConfigNotValid("`$class->name` doesn't have a method `$route->method()`.");
            }

            $routes_by_method[$class->getMethod($route->method)->name][] = $route;
        }

        return $routes_by_method;
    }

    /**
     * Applies the prefix to the routes of a method, and infers their action.
     *
     * @param ReflectionClass<object> $class
     * @param non-empty-string $method
     *     Name of the method that the routes lead to.
     * @param non-empty-list<TransientRoute> $routes
     *
     * @return list<Route>
     *
     * @throws ConfigNotValid
     */
    private function resolve_routes(ReflectionClass $class, string $method, array $routes, ?Prefix $prefix): array
    {
        $resolved = [];

        foreach ($routes as $route) {
            $pattern = ($prefix->route ?? '') . $route->pattern;

            if ($pattern === '') {
                throw new ConfigNotValid(
                    "`$class->name::$method()` has a route with an empty pattern, "
                    . "define a prefix with a route using #[Prefix] or RouteBuilder::prefix()."
                );
            }

            if ($route->action === '') {
                throw new ConfigNotValid("`$class->name::$method()` has a route with a blank action.");
            }

            $action = $route->action ?? ActionResolver::resolve($class->name, $method);

            if ($prefix?->action !== null) {
                $action = $prefix->action . Route::ACTION_SEPARATOR . $action;
            }

            $resolved[] = new Route($pattern, $action, $route->methods, $route->id);
        }

        return $resolved;
    }

    /**
     * An action is responded to by a single method of a single controller.
     *
     * @param ReflectionClass<object> $class
     * @param array<string, ActionDefinition> $actions
     *     The actions of the controller found so far.
     *
     * @throws ConfigNotValid
     */
    private function assert_action_available(
        ReflectionClass $class,
        string $action,
        string $method,
        array $actions
    ): void {
        if (isset($actions[$action]) && $actions[$action]->method !== $method) {
            throw new ConfigNotValid(
                "The action `$action` is defined by both `$class->name::{$actions[$action]->method}()` "
                . "and `$class->name::$method()`."
            );
        }

        $controller = $this->controller_by_action[$action] ?? null;

        if ($controller !== null && $controller !== $class->name) {
            throw new ConfigNotValid(
                "The action `$action` of `$class->name::$method()` is already defined by `$controller`."
            );
        }
    }
}
