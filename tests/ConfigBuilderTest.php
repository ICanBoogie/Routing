<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\CacheStrategy;
use ICanBoogie\Routing\Config;
use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\Config\ControllerDefinition;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteBuilder;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\Compiled;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\Memoize;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\ConfigBuilder\AbstractController;
use Test\ICanBoogie\Routing\ConfigBuilder\ArticlesController;
use Test\ICanBoogie\Routing\ConfigBuilder\Cart;
use Test\ICanBoogie\Routing\ConfigBuilder\ConflictingActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\DuplicateActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\EmptyPatternController;
use Test\ICanBoogie\Routing\ConfigBuilder\MissingPlaceholderController;
use Test\ICanBoogie\Routing\ConfigBuilder\NoRoutesController;
use Test\ICanBoogie\Routing\ConfigBuilder\PhotosController;
use Test\ICanBoogie\Routing\ConfigBuilder\PlainController;
use Test\ICanBoogie\Routing\ConfigBuilder\PrivateRouteController;
use Test\ICanBoogie\Routing\ConfigBuilder\TwoPrefixesController;
use Test\ICanBoogie\Routing\ConfigBuilder\UserPostsController;

use function array_map;
use function iterator_to_array;

#[CoversClass(ConfigBuilder::class)]
#[CoversClass(Config::class)]
#[CoversClass(ControllerDefinition::class)]
final class ConfigBuilderTest extends TestCase
{
    public function test_empty(): void
    {
        $config = new ConfigBuilder()->build();

        $this->assertSame([], $config->controllers);
        $this->assertSame([], $config->controller_by_action);
        $this->assertSame([], iterator_to_array($config->routes));
    }

    public function test_cache_strategy_is_none_by_default(): void
    {
        $config = new ConfigBuilder()->add_responder(PhotosController::class)->build();

        $this->assertInstanceOf(Immutable::class, $config->routes);
    }

    #[DataProvider('provide_cache_strategy')]
    public function test_cache_strategy(CacheStrategy $strategy, string $expected): void
    {
        $config = new ConfigBuilder()
            ->add_responder(PhotosController::class)
            ->cache_strategy($strategy)
            ->build();

        $this->assertInstanceOf($expected, $config->routes);
        $this->assertSame('photos:show', $config->routes->route_for_predicate(new ByAction('photos:show'))?->action);
        $this->assertCount(3, iterator_to_array($config->routes));
    }

    /**
     * @return array<string, array{ CacheStrategy, class-string }>
     */
    public static function provide_cache_strategy(): array
    {
        return [

            'none' => [ CacheStrategy::NONE, Immutable::class ],
            'memoize' => [ CacheStrategy::MEMOIZE, Memoize::class ],
            'compile' => [ CacheStrategy::COMPILE, Compiled::class ],

        ];
    }

    #[DataProvider('provide_exportable_cache_strategy')]
    public function test_exported_config(CacheStrategy $strategy): void
    {
        $config = new ConfigBuilder()
            ->add_responder(PhotosController::class)
            ->cache_strategy($strategy)
            ->build();

        $this->assertEquals($config, eval('return ' . var_export($config, true) . ';'));
    }

    /**
     * @return array<string, array{ CacheStrategy }>
     */
    public static function provide_exportable_cache_strategy(): array
    {
        return [

            'none' => [ CacheStrategy::NONE ],
            'compile' => [ CacheStrategy::COMPILE ],

        ];
    }

    public function test_controller_from_attributes(): void
    {
        $config = new ConfigBuilder()->add_responder(PhotosController::class)->build();

        $this->assertEquals(
            [
                PhotosController::class => new ControllerDefinition([
                    'photos:list' => new ActionDefinition('list'),
                    'photos:show' => new ActionDefinition('show', [ new Path('id', 'int') ]),
                    'photos:create' => new ActionDefinition('create'),
                ])
            ],
            $config->controllers
        );
        $this->assertSame(
            [
                'photos:list' => PhotosController::class,
                'photos:show' => PhotosController::class,
                'photos:create' => PhotosController::class,
            ],
            $config->controller_by_action
        );

        $route = $config->routes->route_for_predicate(new ByAction('photos:show'));

        $this->assertSame('/photos/:id', $route?->pattern->pattern);
        $this->assertSame(RequestMethod::METHOD_GET, $route->methods);
        $this->assertSame(
            RequestMethod::METHOD_POST,
            $config->routes->route_for_predicate(new ByAction('photos:create'))?->methods
        );
    }

    public function test_methods_without_routes_are_not_actions(): void
    {
        $config = new ConfigBuilder()->add_responder(PhotosController::class)->build();

        $this->assertCount(3, $config->controllers[PhotosController::class]->actions);
    }

    public function test_method_with_multiple_routes(): void
    {
        $config = new ConfigBuilder()->add_responder(ArticlesController::class)->build();
        $routes = array_filter(
            iterator_to_array($config->routes),
            fn(Route $route) => $route->action === 'articles:index'
        );

        $this->assertCount(2, $routes);
        $this->assertSame(
            [ '/articles', '/articles/page/:page' ],
            array_map(fn(Route $route) => $route->pattern->pattern, array_values($routes))
        );
        $this->assertSame('articles-page', array_values($routes)[1]->id);
        $this->assertEquals(
            new ActionDefinition('index', [ new Path('page', 'int', null, true, true, null) ]),
            $config->controllers[ArticlesController::class]->actions['articles:index']
        );
    }

    public function test_method_with_multiple_actions(): void
    {
        $config = new ConfigBuilder()->add_responder(ArticlesController::class)->build();
        $actions = $config->controllers[ArticlesController::class]->actions;

        $this->assertEquals(new ActionDefinition('legacy'), $actions['articles:old']);
        $this->assertEquals(new ActionDefinition('legacy'), $actions['articles:older']);
        $this->assertSame(ArticlesController::class, $config->controller_by_action['articles:old']);
        $this->assertSame(ArticlesController::class, $config->controller_by_action['articles:older']);
    }

    public function test_prefix_from_attribute(): void
    {
        $config = new ConfigBuilder()->add_responder(Cart\PhotosController::class)->build();

        $route = $config->routes->route_for_predicate(new ByAction('cart:photos:show'));

        $this->assertSame('/cart/photos/:id', $route?->pattern->pattern);
        $this->assertEquals(
            [
                'cart:photos:show' => new ActionDefinition('show', [ new Path('id', 'int') ]),
                'cart:custom' => new ActionDefinition('explicit'),
            ],
            $config->controllers[Cart\PhotosController::class]->actions
        );
        $this->assertSame(
            '/cart/explicit',
            $config->routes->route_for_predicate(new ByAction('cart:custom'))?->pattern->pattern
        );
    }

    public function test_placeholders_of_the_prefix_are_path_parameters(): void
    {
        $config = new ConfigBuilder()->add_responder(UserPostsController::class)->build();

        $this->assertEquals(
            [ 'user-posts:list' => new ActionDefinition('list', [ new Path('user_id', 'int') ]) ],
            $config->controllers[UserPostsController::class]->actions
        );
        $this->assertSame(
            '/users/:user_id/posts',
            $config->routes->route_for_predicate(new ByAction('user-posts:list'))?->pattern->pattern
        );
    }

    public function test_controller_from_route_builder(): void
    {
        $config = new ConfigBuilder()
            ->add_responder(
                PlainController::class,
                fn(RouteBuilder $r) => $r
                    ->get('/plain', 'list')
                    ->get('/plain/:id', 'show', id: 'plain-show')
                    ->post('/plain', 'create', action: 'plain:store')
            )
            ->build();

        $this->assertEquals(
            [
                'plains:list' => new ActionDefinition('list'),
                'plains:show' => new ActionDefinition('show', [ new Path('id', 'int') ]),
                'plain:store' => new ActionDefinition('create'),
            ],
            $config->controllers[PlainController::class]->actions
        );

        $show = $config->routes->route_for_predicate(new ByAction('plains:show'));

        $this->assertSame('/plain/:id', $show?->pattern->pattern);
        $this->assertSame('plain-show', $show->id);
        $this->assertSame(
            RequestMethod::METHOD_POST,
            $config->routes->route_for_predicate(new ByAction('plain:store'))?->methods
        );
    }

    public function test_prefix_from_route_builder(): void
    {
        $config = new ConfigBuilder()
            ->add_responder(
                PlainController::class,
                fn(RouteBuilder $r) => $r
                    ->get('/:id', 'show')
                    ->prefix(route: '/cart', action: 'cart')
            )
            ->build();

        $this->assertEquals(
            [ 'cart:plains:show' => new ActionDefinition('show', [ new Path('id', 'int') ]) ],
            $config->controllers[PlainController::class]->actions
        );
        $this->assertSame(
            '/cart/:id',
            $config->routes->route_for_predicate(new ByAction('cart:plains:show'))?->pattern->pattern
        );
    }

    public function test_attributes_and_route_builder_are_merged(): void
    {
        $config = new ConfigBuilder()
            ->add_responder(
                PhotosController::class,
                fn(RouteBuilder $r) => $r->delete('/photos/:id', 'show', action: 'photos:delete')
            )
            ->build();

        $actions = $config->controllers[PhotosController::class]->actions;

        $this->assertArrayHasKey('photos:show', $actions);
        $this->assertArrayHasKey('photos:delete', $actions);
        $this->assertEquals($actions['photos:show'], $actions['photos:delete']);
    }

    public function test_a_route_exists_because_of_its_controller(): void
    {
        $config = new ConfigBuilder()->add_responder(PhotosController::class)->build();

        $routes = iterator_to_array($config->routes);

        $this->assertEqualsCanonicalizing(
            array_keys($config->controller_by_action),
            array_map(fn(Route $route) => $route->action, $routes)
        );
    }

    public function test_multiple_controllers(): void
    {
        $config = new ConfigBuilder()
            ->add_responder(PhotosController::class)
            ->add_responder(Cart\PhotosController::class)
            ->build();

        $this->assertSame(
            [ PhotosController::class, Cart\PhotosController::class ],
            array_keys($config->controllers)
        );
        $this->assertSame(Cart\PhotosController::class, $config->controller_by_action['cart:photos:show']);
        $this->assertSame(PhotosController::class, $config->controller_by_action['photos:show']);
    }

    public function test_export(): void
    {
        $config = new ConfigBuilder()
            ->add_responder(PhotosController::class)
            ->add_responder(ArticlesController::class)
            ->add_responder(Cart\PhotosController::class)
            ->build();

        $actual = SetStateHelper::export_import($config);

        $this->assertEquals($config, $actual);
        $this->assertEquals(
            $config->routes->route_for_predicate(new ByAction('photos:show')),
            $actual->routes->route_for_predicate(new ByAction('photos:show'))
        );
    }

    /**
     * @param class-string $controller
     */
    #[DataProvider('provide_not_valid')]
    public function test_not_valid(string $controller, ?\Closure $route_builder, string $message): void
    {
        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage($message);

        new ConfigBuilder()->add_responder($controller, $route_builder);
    }

    /**
     * @return array<string, array{ class-string, \Closure|null, string }>
     */
    public static function provide_not_valid(): array
    {
        return [

            'not a class' => [
                'Not\A\Class',
                null,
                "`Not\A\Class` is not a class."
            ],
            'abstract' => [
                AbstractController::class,
                null,
                "`" . AbstractController::class . "` must be a concrete class."
            ],
            'no routes' => [
                NoRoutesController::class,
                null,
                "`" . NoRoutesController::class . "` has no routes."
            ],
            'route builder, method does not exist' => [
                PlainController::class,
                fn(RouteBuilder $r) => $r->get('/', 'nope'),
                "`" . PlainController::class . "` doesn't have a method `nope()`."
            ],
            'attribute on a method that is not public' => [
                PrivateRouteController::class,
                null,
                "`" . PrivateRouteController::class . "::hidden()` must be a public, non-static, non-abstract method."
            ],
            'same action, two methods' => [
                DuplicateActionController::class,
                null,
                "The action `dup:action` is defined by both `" . DuplicateActionController::class . "::a()` and `"
                . DuplicateActionController::class . "::b()`."
            ],
            'empty pattern, no prefix' => [
                EmptyPatternController::class,
                null,
                "`" . EmptyPatternController::class . "::index()` has a route with an empty pattern, "
                . "define a prefix with a route using #[Prefix] or RouteBuilder::prefix()."
            ],
            'placeholder missing, plan is validated' => [
                MissingPlaceholderController::class,
                null,
                "`" . MissingPlaceholderController::class . "::show()` parameter `\$id`: no `:id` in `/photos/:slug`."
            ],
            'multiple prefixes' => [
                TwoPrefixesController::class,
                null,
                "`" . TwoPrefixesController::class . "` has multiple prefixes, only one is allowed."
            ],
            'prefix from the attribute and the route builder' => [
                Cart\PhotosController::class,
                fn(RouteBuilder $r) => $r->prefix(route: '/other'),
                "`" . Cart\PhotosController::class . "` defines its prefix with both the attribute #[Prefix] "
                . "and RouteBuilder::prefix()."
            ],

        ];
    }

    public function test_route_builder_prefix_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigBuilder()->add_responder(PlainController::class, fn(RouteBuilder $r) => $r->prefix());
    }

    public function test_action_defined_by_another_controller(): void
    {
        $builder = new ConfigBuilder()->add_responder(PhotosController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The action `photos:show` of `" . ConflictingActionController::class
            . "::show()` is already defined by `" . PhotosController::class . "`."
        );

        $builder->add_responder(ConflictingActionController::class);
    }

    public function test_controller_can_only_be_added_once(): void
    {
        $builder = new ConfigBuilder()->add_responder(PhotosController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage("`" . PhotosController::class . "` is already added.");

        $builder->add_responder(PhotosController::class);
    }

    public function test_nothing_is_registered_when_a_controller_is_not_valid(): void
    {
        $builder = new ConfigBuilder();

        try {
            $builder->add_responder(MissingPlaceholderController::class);
            $this->fail("Expected an exception");
        } catch (ConfigNotValid) {
            // expected
        }

        $config = $builder->build();

        $this->assertSame([], $config->controllers);
        $this->assertSame([], $config->controller_by_action);
        $this->assertSame([], iterator_to_array($config->routes));
    }

    public function test_nothing_is_registered_when_a_route_is_not_valid(): void
    {
        $builder = new ConfigBuilder();

        try {
            $builder->add_responder(
                PlainController::class,
                fn(RouteBuilder $r) => $r->get('/plain', 'list')->get('/plain/:id', 'show', action: '')
            );
            $this->fail("Expected an exception");
        } catch (ConfigNotValid $e) {
            $this->assertSame(
                "`" . PlainController::class . "::show()` has a route with a blank action.",
                $e->getMessage()
            );
        }

        $config = $builder->build();

        $this->assertSame([], $config->controllers);
        $this->assertSame([], iterator_to_array($config->routes));
    }

    public function test_a_controller_that_failed_can_be_added_again_when_fixed(): void
    {
        $builder = new ConfigBuilder();

        try {
            $builder->add_responder(PlainController::class, fn(RouteBuilder $r) => $r->get('/', 'nope'));
        } catch (ConfigNotValid) {
            // expected
        }

        $config = $builder
            ->add_responder(PlainController::class, fn(RouteBuilder $r) => $r->get('/plain', 'list'))
            ->build();

        $this->assertArrayHasKey('plains:list', $config->controller_by_action);
    }
}
