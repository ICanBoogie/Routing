<?php

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use ICanBoogie\Routing\RouteProvider\ByUri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\SetStateHelper;

use function array_find;
use function iterator_to_array;

/**
 * Providers behave the same, whatever the way they find routes. Each provider extends this test case, and
 * the same tests are run against the provider, and against the provider exported and imported.
 *
 * The routes are not ambiguous, because precedence differs between providers, see docs/RouteProviders.md.
 */
abstract class RouteProviderTestCase extends TestCase
{
    /**
     * @param Route[] $routes
     */
    abstract protected static function create_provider(array $routes): RouteProvider;

    /**
     * @var Route[]
     */
    protected static array $routes;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$routes = self::make_routes();
    }

    /**
     * @return Route[]
     */
    private static function make_routes(): array
    {
        $routes = [
            new Route('/', 'page:home', id: 'home'),
            new Route('/about.html', 'page:about'),
            new Route('/articles/new', 'articles:new', RequestMethod::METHOD_GET),
            new Route('/articles', 'articles:list', RequestMethod::METHOD_GET, 'articles'),
            new Route('/articles', 'articles:create', [ RequestMethod::METHOD_POST, RequestMethod::METHOD_PATCH ]),
            new Route('/articles/<nid:\d+>', 'articles:show', RequestMethod::METHOD_GET, 'article'),
            new Route('/articles/<nid:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE),
            new Route('/articles/:slug', 'articles:any', RequestMethod::METHOD_PUT),
            new Route('/admin/articles/<\d+>/edit', 'admin:articles:edit'),
            new Route('/api/articles/:id/active', 'api:articles:activate', RequestMethod::METHOD_PUT),
            new Route('/files/*', 'files:serve'),
        ];

        // Enough dynamic routes to span several chunks.
        for ($i = 0; $i < 70; $i++) {
            $routes[] = new Route("/section-$i/<id:\d+>/:slug", "section:$i", id: "section_$i");
        }

        return $routes;
    }

    /**
     * @return array<string, array{ string, RequestMethod, string|null, array<string|int, string>, array<string|int, string|string[]> }>
     *     Where _value_ is the URI, the method, the action of the expected route (or `null`), the expected
     *     path params and the expected query params.
     */
    public static function provide_uris(): array
    {
        return [
            'root' => [ '/', RequestMethod::METHOD_GET, 'page:home', [], [] ],
            'static, method any' => [ '/about.html', RequestMethod::METHOD_ANY, 'page:about', [], [] ],
            'static, route without method' => [ '/about.html', RequestMethod::METHOD_DELETE, 'page:about', [], [] ],
            'static before dynamic' => [ '/articles/new', RequestMethod::METHOD_GET, 'articles:new', [], [] ],
            'static, method mismatch' => [ '/articles/new', RequestMethod::METHOD_POST, null, [], [] ],
            'static, same path, GET' => [ '/articles', RequestMethod::METHOD_GET, 'articles:list', [], [] ],
            'static, same path, POST' => [ '/articles', RequestMethod::METHOD_POST, 'articles:create', [], [] ],
            'static, same path, PATCH' => [ '/articles', RequestMethod::METHOD_PATCH, 'articles:create', [], [] ],
            'static, same path, no method' => [ '/articles', RequestMethod::METHOD_PUT, null, [], [] ],
            'static, query string' => [
                '/about.html?a=1',
                RequestMethod::METHOD_GET,
                'page:about',
                [],
                [ 'a' => '1' ]
            ],
            'dynamic' => [ '/articles/123', RequestMethod::METHOD_GET, 'articles:show', [ 'nid' => '123' ], [] ],
            'dynamic, same pattern' => [
                '/articles/123',
                RequestMethod::METHOD_DELETE,
                'articles:delete',
                [ 'nid' => '123' ],
                []
            ],
            'dynamic, path params are removed from query' => [
                '/articles/123?q=1&nid=2',
                RequestMethod::METHOD_GET,
                'articles:show',
                [ 'nid' => '123' ],
                [ 'q' => '1' ]
            ],
            'dynamic, method mismatch' => [ '/articles/123', RequestMethod::METHOD_POST, null, [], [] ],
            'dynamic, next route' => [
                '/articles/hello-world',
                RequestMethod::METHOD_PUT,
                'articles:any',
                [ 'slug' => 'hello-world' ],
                []
            ],
            'dynamic, anonymous param' => [
                '/admin/articles/123/edit',
                RequestMethod::METHOD_GET,
                'admin:articles:edit',
                [ 0 => '123' ],
                []
            ],
            'dynamic, several params' => [
                '/api/articles/7/active',
                RequestMethod::METHOD_PUT,
                'api:articles:activate',
                [ 'id' => '7' ],
                []
            ],
            'dynamic, wildcard' => [
                '/files/a/b/c.txt',
                RequestMethod::METHOD_GET,
                'files:serve',
                [ 'all' => 'a/b/c.txt' ],
                []
            ],
            'dynamic, first chunk' => [
                '/section-0/1/a',
                RequestMethod::METHOD_GET,
                'section:0',
                [ 'id' => '1', 'slug' => 'a' ],
                []
            ],
            'dynamic, middle chunk' => [
                '/section-31/1/a?x=1',
                RequestMethod::METHOD_GET,
                'section:31',
                [ 'id' => '1', 'slug' => 'a' ],
                [ 'x' => '1' ]
            ],
            'dynamic, last chunk' => [
                '/section-69/1/a',
                RequestMethod::METHOD_GET,
                'section:69',
                [ 'id' => '1', 'slug' => 'a' ],
                []
            ],
            'dynamic, no route' => [ '/section-70/1/a', RequestMethod::METHOD_GET, null, [], [] ],
            'no route' => [ '/nothing/here', RequestMethod::METHOD_GET, null, [], [] ],
        ];
    }

    /**
     * Runs the assertions against the shared providers.
     *
     * @param callable(RouteProvider, string): void $assert
     * @param Route[] $routes
     */
    private function assert_provider(callable $assert, array $routes): void
    {
        $suts = [
            'live' => $live = static::create_provider($routes),
            'import' => SetStateHelper::export_import($live),
        ];

        foreach ($suts as $label => $sut) {
            $assert($sut, $label);
        }
    }

    /**
     * @param array<string|int, string> $expected_path_params
     * @param array<string|int, string|string[]> $expected_query_params
     */
    #[DataProvider('provide_uris')]
    public function test_by_uri(
        string $uri,
        RequestMethod $method,
        ?string $expected_action,
        array $expected_path_params,
        array $expected_query_params,
    ): void {
        $this->assert_provider(
            function (RouteProvider $provider, string $label) use (
                $uri,
                $method,
                $expected_action,
                $expected_path_params,
                $expected_query_params,
            ): void {
                $predicate = new ByUri($uri, $method);
                $route = $provider->route_for_predicate($predicate);

                $this->assertSame($expected_action, $route?->action, $label);

                if (!$expected_action) {
                    return;
                }

                $expected = array_find(self::$routes, fn(Route $r) => $r->action === $expected_action);

                $this->assertEquals($expected, $route, $label);
                $this->assertSame($expected_path_params, $predicate->path_params, $label);
                $this->assertSame($expected_query_params, $predicate->query_params, $label);
            },
            self::$routes
        );
    }

    /**
     * @return array<string, array{ string, string|null }>
     *     Where _value_ is the identifier and the action of the expected route (or `null`).
     */
    public static function provide_ids(): array
    {
        return [
            'first' => [ 'home', 'page:home' ],
            'static' => [ 'articles', 'articles:list' ],
            'dynamic' => [ 'article', 'articles:show' ],
            'last' => [ 'section_69', 'section:69' ],
            'an action is not an identifier' => [ 'page:home', null ],
            'unknown' => [ 'nope', null ],
        ];
    }

    #[DataProvider('provide_ids')]
    public function test_by_id(string $id, ?string $expected_action): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label) use ($id, $expected_action): void {
            $route = $provider->route_for_predicate(new ById($id));

            $this->assertSame($expected_action, $route?->action, $label);
        }, self::$routes);
    }

    /**
     * @return array<string, array{ string, string|null }>
     *     Where _value_ is the action and the identifier of the expected route (or `null`).
     */
    public static function provide_actions(): array
    {
        return [
            'first' => [ 'page:home', 'home' ],
            'without identifier' => [ 'page:about', '' ],
            'last' => [ 'section:69', 'section_69' ],
            'an identifier is not an action' => [ 'home', null ],
            'unknown' => [ 'nope', null ],
        ];
    }

    #[DataProvider('provide_actions')]
    public function test_by_action(string $action, ?string $expected_id): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label) use ($action, $expected_id): void {
            $route = $provider->route_for_predicate(new ByAction($action));

            $this->assertSame($expected_id === null ? null : $action, $route?->action, $label);
            $this->assertSame($expected_id === '' ? null : $expected_id, $route?->id, $label);
        }, self::$routes);
    }

    public function test_by_id_or_action(): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label): void {
            $this->assertSame('page:home', $provider->route_for_predicate(new ByIdOrAction('home'))?->action, $label);
            $this->assertSame('page:home', $provider->route_for_predicate(new ByIdOrAction('page:home'))?->action, $label);
            $this->assertSame('page:about', $provider->route_for_predicate(new ByIdOrAction('page:about'))?->action, $label);
            $this->assertNull($provider->route_for_predicate(new ByIdOrAction('nope')), $label);
        }, self::$routes);
    }

    public function test_the_last_route_wins_for_id_and_action(): void
    {
        $routes = [
            new Route('/a', 'first', id: 'same'),
            new Route('/b', 'second', id: 'same'),
            new Route('/c', 'first'),
        ];

        $this->assert_provider(function (RouteProvider $provider, string $label) use ($routes): void {
            $this->assertEquals($routes[1], $provider->route_for_predicate(new ById('same')), $label);
            $this->assertEquals($routes[2], $provider->route_for_predicate(new ByAction('first')), $label);
        }, $routes);
    }

    public function test_other_predicates_are_tested_against_every_route(): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label): void {
            $route = $provider->route_for_predicate(fn(Route $route): bool => $route->action === 'files:serve');

            $this->assertSame('files:serve', $route?->action, $label);
            $this->assertNull($provider->route_for_predicate(fn(Route $route): bool => false), $label);
        }, self::$routes);
    }

    public function test_iteration_preserves_routes_and_order(): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label): void {
            $this->assertEquals(self::$routes, [ ...$provider ], $label);
        }, self::$routes);
    }

    public function test_extensions_are_preserved(): void
    {
        $extension = (object)[ 'a' => 1 ];

        $this->assert_provider(function (RouteProvider $provider, string $label) use ($extension): void {
            $route = $provider->route_for_predicate(new ByUri('/'));

            $this->assertEquals([ $extension ], $route?->extensions, $label);
        }, [ new Route('/', 'home', extensions: [ $extension ]) ]);
    }

    public function test_empty(): void
    {
        $this->assert_provider(function (RouteProvider $provider, string $label): void {
            $this->assertNull($provider->route_for_predicate(new ByUri('/')), $label);
            $this->assertSame([], iterator_to_array($provider), $label);
        }, []);
    }

    public function test_provider_can_be_exported_after_use(): void
    {
        $provider = static::create_provider(self::$routes);
        $provider->route_for_predicate(new ByUri('/articles/123', RequestMethod::METHOD_GET));
        $provider->route_for_predicate(new ByUri('/section-69/1/a'));

        $imported = SetStateHelper::export_import($provider);

        $this->assertEquals(self::$routes, [ ...$imported ]);
        $this->assertSame(
            'articles:show',
            $imported->route_for_predicate(new ByUri('/articles/123', RequestMethod::METHOD_GET))?->action
        );
    }
}
