<?php

namespace Test\ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Attribute\Connect;
use ICanBoogie\Routing\Attribute\Delete;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Head;
use ICanBoogie\Routing\Attribute\Options;
use ICanBoogie\Routing\Attribute\Patch;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Put;
use ICanBoogie\Routing\Attribute\Route;
use ICanBoogie\Routing\Attribute\Trace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_map;

#[CoversClass(Route::class)]
#[CoversClass(Connect::class)]
#[CoversClass(Delete::class)]
#[CoversClass(Get::class)]
#[CoversClass(Head::class)]
#[CoversClass(Options::class)]
#[CoversClass(Patch::class)]
#[CoversClass(Post::class)]
#[CoversClass(Put::class)]
#[CoversClass(Trace::class)]
final class RouteTest extends TestCase
{
    public function test_route(): void
    {
        $route = new Route(
            pattern: '/articles/:id',
            action: 'articles:show',
            methods: [ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ],
            id: 'article',
        );

        $this->assertSame('/articles/:id', $route->pattern);
        $this->assertSame('articles:show', $route->action);
        $this->assertSame([ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ], $route->methods);
        $this->assertSame('article', $route->id);
    }

    public function test_route_defaults(): void
    {
        $route = new Route('/');

        $this->assertNull($route->action);
        $this->assertSame(RequestMethod::METHOD_ANY, $route->methods);
        $this->assertNull($route->id);
    }

    /**
     * @param class-string<Route> $class
     */
    #[DataProvider('provide_shortcuts')]
    public function test_shortcut(string $class, RequestMethod $method): void
    {
        $route = new $class('/articles/:id', 'articles:show', 'article');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/articles/:id', $route->pattern);
        $this->assertSame('articles:show', $route->action);
        $this->assertSame($method, $route->methods);
        $this->assertSame('article', $route->id);
    }

    /**
     * @param class-string<Route> $class
     */
    #[DataProvider('provide_shortcut_classes')]
    public function test_shortcut_pattern_is_optional(string $class): void
    {
        $route = new $class();

        $this->assertSame('', $route->pattern);
        $this->assertNull($route->action);
        $this->assertNull($route->id);
    }

    /**
     * @return array<string, array{ class-string<Route> }>
     */
    public static function provide_shortcut_classes(): array
    {
        return array_map(fn(array $args): array => [ $args[0] ], self::provide_shortcuts());
    }

    /**
     * @return array<string, array{ class-string<Route>, RequestMethod }>
     */
    public static function provide_shortcuts(): array
    {
        return [

            'connect' => [ Connect::class, RequestMethod::METHOD_CONNECT ],
            'delete' => [ Delete::class, RequestMethod::METHOD_DELETE ],
            'get' => [ Get::class, RequestMethod::METHOD_GET ],
            'head' => [ Head::class, RequestMethod::METHOD_HEAD ],
            'options' => [ Options::class, RequestMethod::METHOD_OPTIONS ],
            'patch' => [ Patch::class, RequestMethod::METHOD_PATCH ],
            'post' => [ Post::class, RequestMethod::METHOD_POST ],
            'put' => [ Put::class, RequestMethod::METHOD_PUT ],
            'trace' => [ Trace::class, RequestMethod::METHOD_TRACE ],

        ];
    }

    /**
     * Routes are attributes of methods, and they are repeatable.
     *
     * @param class-string<Route> $class
     */
    #[DataProvider('provide_attributes')]
    public function test_attribute_flags(string $class): void
    {
        $attribute = new ReflectionClass($class)->getAttributes(Attribute::class)[0]->newInstance();

        $this->assertSame(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE, $attribute->flags);
    }

    /**
     * @return array<string, array{ class-string<Route> }>
     */
    public static function provide_attributes(): array
    {
        return [ 'route' => [ Route::class ] ] + array_map(
            fn(array $case) => [ $case[0] ],
            self::provide_shortcuts()
        );
    }

    public function test_read_from_a_method(): void
    {
        $controller = new class {
            #[Get('/articles/:id', action: 'articles:show')]
            #[Post('/articles/:id', action: 'articles:update')]
            public function show(): void
            {
            }
        };

        $attributes = new ReflectionClass($controller)->getMethod('show')->getAttributes(Route::class, 2);

        $this->assertCount(2, $attributes);
        $this->assertInstanceOf(Get::class, $attributes[0]->newInstance());
        $this->assertInstanceOf(Post::class, $attributes[1]->newInstance());
    }
}
