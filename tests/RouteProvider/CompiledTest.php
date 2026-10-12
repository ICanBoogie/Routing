<?php

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use ICanBoogie\Routing\RouteProvider\ByUri;
use ICanBoogie\Routing\RouteProvider\Compiled;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\Memoize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function iterator_to_array;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class CompiledTest extends TestCase
{
    private Compiled $compiled;

    /**
     * @var Route[]
     */
    private array $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $routes = [
            new Route('/', 'page:home', id: 'home'),
            new Route('/about.html', 'page:about'),
            new Route('/articles/new', 'articles:new', RequestMethod::METHOD_GET),
            new Route('/articles', 'articles:list', RequestMethod::METHOD_GET, 'articles'),
            new Route('/articles', 'articles:create', [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]),
            new Route('/articles/<nid:\d+>', 'articles:show', RequestMethod::METHOD_GET, 'article'),
            new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),
            new Route('/articles/:slug', 'articles:any'),
            new Route('/admin/articles/<\d+>/edit', 'admin:articles:edit'),
            new Route('/api/:constructor/:id/active', 'api:nodes:activate', RequestMethod::METHOD_PUT),
            new Route('/api/articles/:id/active', 'api:articles:activate', RequestMethod::METHOD_PUT),
            new Route('/files/*', 'files:serve'),
        ];

        for ($i = 0; $i < 70; $i++) {
            $routes[] = new Route("/section-$i/<id:\d+>/:slug", "section:$i", id: "section_$i");
        }

        $this->routes = $routes;
        $this->compiled = self::load($routes);
    }

    /**
     * @param Route[] $routes
     */
    private static function load(array $routes): Compiled
    {
        $file = sys_get_temp_dir() . '/' . uniqid('compiled-', true) . '.php';
        file_put_contents($file, Compiled::dump($routes));

        try {
            return new Compiled(require $file);
        } finally {
            unlink($file);
        }
    }

    public function test_iterator_preserves_routes_and_order(): void
    {
        $actual = iterator_to_array($this->compiled);

        $this->assertCount(count($this->routes), $actual);

        foreach ($this->routes as $i => $expected) {
            $this->assertEquals($expected, $actual[$i]);
        }

        // Routes are created once.
        $this->assertSame($actual[3], iterator_to_array($this->compiled)[3]);
    }

    /**
     * @return array<string, array{ string, RequestMethod|null }>
     */
    public static function provide_uris(): array
    {
        return [
            [ '/', null ],
            [ '/about.html', RequestMethod::METHOD_GET ],
            [ '/articles/new', RequestMethod::METHOD_GET ],
            [ '/articles/new', RequestMethod::METHOD_POST ],
            [ '/articles', RequestMethod::METHOD_GET ],
            [ '/articles', RequestMethod::METHOD_POST ],
            [ '/articles', RequestMethod::METHOD_PUT ],
            [ '/articles/123?q=1&nid=2', RequestMethod::METHOD_GET ],
            [ '/articles/123', RequestMethod::METHOD_DELETE ],
            [ '/articles/123', RequestMethod::METHOD_PUT ],
            [ '/articles/hello-world', RequestMethod::METHOD_GET ],
            [ '/admin/articles/123/edit', null ],
            [ '/api/articles/123/active', RequestMethod::METHOD_PUT ],
            [ '/api/articles/123/active', RequestMethod::METHOD_GET ],
            [ '/files/a/b/c.txt', null ],
            [ '/section-0/1/a', null ],
            [ '/section-31/1/a?x=1', null ],
            [ '/section-69/1/a', null ],
            [ '/section-70/1/a', null ],
            [ '/nothing/here', null ],
        ];
    }

    #[DataProvider('provide_uris')]
    public function test_by_uri_behaves_like_memoize(string $uri, ?RequestMethod $method): void
    {
        $memoize = new Memoize(new Immutable($this->routes));

        $expected = $memoize->route_for_predicate($e = new ByUri($uri, $method ?? RequestMethod::METHOD_ANY));
        $actual = $this->compiled->route_for_predicate($a = new ByUri($uri, $method ?? RequestMethod::METHOD_ANY));

        $this->assertEquals($expected, $actual);
        $this->assertSame($e->path_params, $a->path_params);
        $this->assertSame($e->query_params, $a->query_params);
    }

    public function test_by_id(): void
    {
        $this->assertEquals($this->routes[0], $this->compiled->route_for_predicate(new ById('home')));
        $this->assertEquals($this->routes[5], $this->compiled->route_for_predicate(new ById('article')));
        $this->assertNull($this->compiled->route_for_predicate(new ById('page:home')));
        $this->assertNull($this->compiled->route_for_predicate(new ById('nope')));
    }

    public function test_by_action(): void
    {
        $this->assertEquals($this->routes[1], $this->compiled->route_for_predicate(new ByAction('page:about')));
        $this->assertNull($this->compiled->route_for_predicate(new ByAction('home')));
    }

    public function test_by_id_or_action(): void
    {
        $this->assertEquals($this->routes[0], $this->compiled->route_for_predicate(new ByIdOrAction('home')));
        $this->assertEquals($this->routes[0], $this->compiled->route_for_predicate(new ByIdOrAction('page:home')));
        $this->assertNull($this->compiled->route_for_predicate(new ByIdOrAction('nope')));
    }

    public function test_other_predicates_are_tested_against_every_route(): void
    {
        $route = $this->compiled->route_for_predicate(
            fn(Route $route): bool => $route->action === 'files:serve'
        );

        $this->assertEquals($this->routes[11], $route);
        $this->assertNull($this->compiled->route_for_predicate(fn(Route $route): bool => false));
    }

    public function test_extensions_are_preserved(): void
    {
        $extension = (object) [ 'a' => 1 ];
        $compiled = self::load([ new Route('/', 'home', extensions: [ $extension ]) ]);

        $this->assertEquals([ $extension ], $compiled->route_for_predicate(new ByAction('home'))->extensions);
    }

    public function test_empty(): void
    {
        $compiled = self::load([]);

        $this->assertNull($compiled->route_for_predicate(new ByUri('/')));
        $this->assertNull($compiled->route_for_predicate(new ById('home')));
        $this->assertSame([], iterator_to_array($compiled));
    }
}
