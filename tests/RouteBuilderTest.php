<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\Config\TransientRoute;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\RouteBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(RouteBuilder::class)]
#[CoversClass(TransientRoute::class)]
#[UsesClass(Prefix::class)]
final class RouteBuilderTest extends TestCase
{
    public function test_empty(): void
    {
        $builder = new RouteBuilder();

        $this->assertNull($builder->prefix);
        $this->assertSame([], $builder->routes);
    }

    public function test_route(): void
    {
        $builder = new RouteBuilder()->route(
            '/articles/:id',
            'show',
            [ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ],
            'articles:show',
            'article',
        );

        $this->assertEquals(
            [
                new TransientRoute(
                    '/articles/:id',
                    'show',
                    [ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ],
                    'articles:show',
                    'article'
                )
            ],
            $builder->routes
        );
    }

    public function test_route_defaults(): void
    {
        $route = new RouteBuilder()->route('/', 'index')->routes[0];

        $this->assertSame(RequestMethod::METHOD_ANY, $route->methods);
        $this->assertNull($route->action);
        $this->assertNull($route->id);
    }

    #[DataProvider('provide_verbs')]
    public function test_verb(string $verb, RequestMethod $expected): void
    {
        $builder = new RouteBuilder();

        $this->assertSame($builder, $builder->$verb('/articles/:id', 'show', 'articles:show', 'article'));
        $this->assertEquals(
            [ new TransientRoute('/articles/:id', 'show', $expected, 'articles:show', 'article') ],
            $builder->routes
        );
    }

    /**
     * @return array<string, array{ string, RequestMethod }>
     */
    public static function provide_verbs(): array
    {
        return [

            'any' => [ 'any', RequestMethod::METHOD_ANY ],
            'get' => [ 'get', RequestMethod::METHOD_GET ],
            'post' => [ 'post', RequestMethod::METHOD_POST ],
            'put' => [ 'put', RequestMethod::METHOD_PUT ],
            'patch' => [ 'patch', RequestMethod::METHOD_PATCH ],
            'delete' => [ 'delete', RequestMethod::METHOD_DELETE ],
            'head' => [ 'head', RequestMethod::METHOD_HEAD ],
            'options' => [ 'options', RequestMethod::METHOD_OPTIONS ],
            'connect' => [ 'connect', RequestMethod::METHOD_CONNECT ],
            'trace' => [ 'trace', RequestMethod::METHOD_TRACE ],

        ];
    }

    public function test_routes_keep_their_order(): void
    {
        $builder = new RouteBuilder()->get('/a', 'a')->post('/b', 'b')->get('/c', 'c');

        $this->assertSame([ 'a', 'b', 'c' ], array_map(fn(TransientRoute $r) => $r->method, $builder->routes));
    }

    public function test_prefix(): void
    {
        $builder = new RouteBuilder();

        $this->assertSame($builder, $builder->prefix(route: '/cart', action: 'cart'));
        $this->assertSame('/cart', $builder->prefix?->route);
        $this->assertSame('cart', $builder->prefix->action);
    }

    public function test_prefix_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RouteBuilder()->prefix();
    }

    public function test_prefix_can_be_defined_once(): void
    {
        $builder = new RouteBuilder()->prefix(route: '/cart');

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage("The prefix is already defined.");

        $builder->prefix(action: 'cart');
    }

    public function test_prefix_applies_wherever_the_call_sits(): void
    {
        $builder = new RouteBuilder()->get('/a', 'a')->prefix(route: '/cart')->get('/b', 'b');

        $this->assertSame('/cart', $builder->prefix?->route);
        $this->assertCount(2, $builder->routes);
    }
}
