<?php

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\Compiled;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ReflectionClass;
use ReflectionProperty;

use function array_filter;
use function array_keys;
use function array_map;

final class CompiledTest extends RouteProviderTestCase
{
    protected static function create_provider(array $routes): RouteProvider
    {
        return new Compiled(new Immutable($routes));
    }

    /**
     * Compiled copies the state of Route and Pattern into arrays, and rebuilds the objects from them. If
     * a property is added to either class, Compiled has to be updated, see AGENTS.md.
     */
    public function test_compiled_data_covers_the_state_of_route_and_pattern(): void
    {
        $compiled = new Compiled(new Immutable([ new Route('/articles/<id:\d+>', 'articles:show') ]));
        $data = new ReflectionProperty($compiled, 'routes')->getValue($compiled)[0];

        $this->assertSame(
            self::property_names(Route::class),
            self::sorted(array_keys($data)),
            'Compiled does not store the state of Route, update Compiled::__construct() and make_route().'
        );

        $this->assertSame(
            self::property_names(Pattern::class),
            self::sorted(array_keys($data['pattern'])),
            'Compiled does not store the state of Pattern, update Compiled::__construct() and make_route().'
        );
    }

    /**
     * @param string[] $names
     *
     * @return string[]
     */
    private static function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    /**
     * @param class-string $class
     *
     * @return string[]
     */
    private static function property_names(string $class): array
    {
        return self::sorted(array_map(
            fn(ReflectionProperty $property): string => $property->getName(),
            array_filter(
                new ReflectionClass($class)->getProperties(),
                fn(ReflectionProperty $property): bool => !$property->isStatic()
            )
        ));
    }
}
