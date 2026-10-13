<?php

namespace ICanBoogie\Routing;

use Closure;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\Config\ControllerDefinition;
use ICanBoogie\Routing\Config\TransientRoute;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use LogicException;
use olvlvl\ComposerAttributeCollector\Attributes;
use ReflectionAttribute;
use ReflectionClass;

use function array_map;
use function class_exists;
use function count;
use function sprintf;

/**
 * Builds the routing {@see Config}.
 *
 * Controllers are plain classes, they are added with {@see add_controller()}, which reads the
 * attributes of the class and its methods, and optionally a {@see RouteBuilder}. Everything is
 * validated and resolved right away, so that mistakes are reported when the configuration is built,
 * not when requests are handled.
 *
 * Routes exist because a controller defines them: there is no way to add a route without the
 * action that answers it.
 *
 * ```php
 * $config = new ConfigBuilder()
 *     ->add_controller(PhotosController::class)
 *     ->add_controller(ContactController::class, fn(RouteBuilder $r) => $r
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

    private CacheStrategy $cache_strategy = CacheStrategy::NONE;

    /**
     * @var array<string, string>
     *     Where _key_ is the id of a route and _value_ the controller and the method it leads to.
     */
    private array $route_ids = [];

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
    public function add_controller(string $controller, ?Closure $route_builder = null): self
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
        $origins = [];
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
                $origins[] = "$class->name::$method_name()";
            }
        }

        $ids = $this->assert_ids_available($routes, $origins);

        $this->routes->add_routes(...$routes);
        $this->route_ids += $ids;

        $this->controllers[$class->name] = new ControllerDefinition($actions);

        foreach ($actions as $action => $_) {
            $this->controller_by_action[$action] = $class->name;
        }

        return $this;
    }

    /**
     * Adds the controllers found with the {@see Attribute\Route} attributes of their methods.
     *
     * The controllers are discovered by `olvlvl/composer-attribute-collector`, which is up to the
     * application to require. The controllers already added with {@see add_controller()} are
     * skipped, so a controller can be added by hand, e.g. with a {@see RouteBuilder}, before or
     * after discovery.
     *
     * @return $this
     *
     * @throws ConfigNotValid
     * @throws LogicException if the attribute collector is not available.
     */
    public function discover_controllers(): self
    {
        if (!class_exists(Attributes::class)) {
            throw new LogicException(
                sprintf(
                    "Unable to configure using attributes, the class %s is not available, "
                    . "require olvlvl/composer-attribute-collector.",
                    Attributes::class
                )
            );
        }

        foreach (Attributes::filterTargetMethods(
            Attributes::predicateForAttributeInstanceOf(Attribute\Route::class)
        ) as $target) {
            $class = $target->class;

            if (!isset($this->controllers[$class])) {
                $this->add_controller($class);
            }
        }

        return $this;
    }

    /**
     * Defines how the routes are served, {@see CacheStrategy::NONE} by default. Use
     * {@see CacheStrategy::COMPILE} in production.
     *
     * Only a configuration built with {@see CacheStrategy::COMPILE} or {@see CacheStrategy::NONE}
     * can be exported with `var_export()`.
     *
     * @return $this
     */
    public function cache_strategy(CacheStrategy $strategy): self
    {
        $this->cache_strategy = $strategy;

        return $this;
    }

    /**
     * Builds the configuration.
     *
     * In production, the routes are compiled and the configuration is cached as a PHP file, so the
     * work is done once, when the configuration is built, and not by every request. See
     * {@see cache_strategy()}.
     */
    public function build(): Config
    {
        return new Config(
            $this->build_routes(),
            $this->controllers,
            $this->controller_by_action,
        );
    }

    private function build_routes(): RouteProvider
    {
        return match ($this->cache_strategy) {
            CacheStrategy::NONE => new RouteProvider\Immutable($this->routes),
            CacheStrategy::MEMOIZE => new RouteProvider\Memoize(new RouteProvider\Immutable($this->routes)),
            CacheStrategy::COMPILE => new RouteProvider\Compiled($this->routes),
        };
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
            $pattern = ($prefix->pattern ?? '') . $route->pattern;

            if ($pattern === '') {
                throw new ConfigNotValid(
                    "`$class->name::$method()` has a route with an empty pattern, "
                    . "define a prefix with a pattern using #[Prefix] or RouteBuilder::prefix()."
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
     * The id of a route is unique, and distinct from the action of the other routes, otherwise
     * finding a route with an id or an action is ambiguous. Routes created by hand, outside of the
     * builder, are not known.
     *
     * @param list<Route> $routes
     *     The routes of the controller being added.
     * @param list<string> $origins
     *     The method that each route of `$routes` leads to, at the same position.
     *
     * @return array<string, string>
     *     The ids of the routes, with the method they lead to.
     *
     * @throws ConfigNotValid
     */
    private function assert_ids_available(array $routes, array $origins): array
    {
        $ids = [];

        foreach ($routes as $i => $route) {
            if ($route->id === null) {
                continue;
            }

            $id = $route->id;
            $other = $ids[$id] ?? $this->route_ids[$id] ?? null;

            if ($other !== null) {
                throw new ConfigNotValid("The id `$id` of `$origins[$i]` is already used by `$other`.");
            }

            $ids[$id] = $origins[$i];

            foreach ($routes as $j => $other_route) {
                if ($j !== $i && $other_route->action === $id) {
                    throw new ConfigNotValid(
                        "The id `$id` of `$origins[$i]` is also the action of `$origins[$j]`."
                    );
                }
            }

            $controller = $this->controller_by_action[$id] ?? null;

            if ($controller !== null) {
                throw new ConfigNotValid("The id `$id` of `$origins[$i]` is also an action of `$controller`.");
            }
        }

        foreach ($routes as $i => $route) {
            $other = $this->route_ids[$route->action] ?? null;

            if ($other !== null) {
                throw new ConfigNotValid(
                    "The action `$route->action` of `$origins[$i]` is also the id of `$other`."
                );
            }
        }

        return $ids;
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
