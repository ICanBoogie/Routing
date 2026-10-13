<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\ActionResolver;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\CacheStrategy;
use ICanBoogie\Routing\Config;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\Config\ControllerDefinition;
use ICanBoogie\Routing\Config\TransientRoute;
use ICanBoogie\Routing\Exception\ConfigNotValid;
use ICanBoogie\Routing\ParameterAbstract;
use ICanBoogie\Routing\Parameter\Path;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\PlanBuilder;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteBuilder;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\Compiled;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\Memoize;
use ICanBoogie\Routing\RouteProvider\MemoizeByAction;
use ICanBoogie\Routing\RouteProvider\Mutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use Test\ICanBoogie\Routing\ConfigBuilder\AbstractController;
use Test\ICanBoogie\Routing\ConfigBuilder\ActionOfAnIdController;
use Test\ICanBoogie\Routing\ConfigBuilder\ArticlesController;
use Test\ICanBoogie\Routing\ConfigBuilder\Cart;
use Test\ICanBoogie\Routing\ConfigBuilder\ConflictingActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\DuplicateActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\EmptyPatternController;
use Test\ICanBoogie\Routing\ConfigBuilder\FirstIdController;
use Test\ICanBoogie\Routing\ConfigBuilder\IdIsActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\IdOfAnActionController;
use Test\ICanBoogie\Routing\ConfigBuilder\MissingPlaceholderController;
use Test\ICanBoogie\Routing\ConfigBuilder\NoRoutesController;
use Test\ICanBoogie\Routing\ConfigBuilder\OwnActionIdController;
use Test\ICanBoogie\Routing\ConfigBuilder\PhotosController;
use Test\ICanBoogie\Routing\ConfigBuilder\PlainController;
use Test\ICanBoogie\Routing\ConfigBuilder\PrivateRouteController;
use Test\ICanBoogie\Routing\ConfigBuilder\SameIdController;
use Test\ICanBoogie\Routing\ConfigBuilder\SecondIdController;
use Test\ICanBoogie\Routing\ConfigBuilder\TwoPrefixesController;
use Test\ICanBoogie\Routing\ConfigBuilder\UserPostsController;
use Test\ICanBoogie\Routing\Discovery\InvoicesController;
use Test\ICanBoogie\Routing\Discovery\ReportsController;
use function array_map;
use function iterator_to_array;

#[CoversClass(ConfigBuilder::class)]
#[CoversClass(Config::class)]
#[CoversClass(ControllerDefinition::class)]
#[UsesClass(ActionResolver::class)]
#[UsesClass(Get::class)]
#[UsesClass(Post::class)]
#[UsesClass(Prefix::class)]
#[UsesClass(\ICanBoogie\Routing\Attribute\Route::class)]
#[UsesClass(ActionDefinition::class)]
#[UsesClass(TransientRoute::class)]
#[UsesClass(ParameterAbstract::class)]
#[UsesClass(Pattern::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(Route::class)]
#[UsesClass(RouteBuilder::class)]
#[UsesClass(ByAction::class)]
#[UsesClass(Compiled::class)]
#[UsesClass(Immutable::class)]
#[UsesClass(Mutable::class)]
#[UsesClass(Memoize::class)]
#[UsesClass(MemoizeByAction::class)]
#[UsesClass(ById::class)]
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
        $config = new ConfigBuilder()->add_controller(PhotosController::class)->build();

        $this->assertInstanceOf(Immutable::class, $config->routes);
    }

    #[DataProvider('provide_cache_strategy')]
    public function test_cache_strategy(CacheStrategy $strategy, string $expected): void
    {
        $config = new ConfigBuilder()
            ->add_controller(PhotosController::class)
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
            ->add_controller(PhotosController::class)
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
        $config = new ConfigBuilder()->add_controller(PhotosController::class)->build();

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
        $config = new ConfigBuilder()->add_controller(PhotosController::class)->build();

        $this->assertCount(3, $config->controllers[PhotosController::class]->actions);
    }

    public function test_method_with_multiple_routes(): void
    {
        $config = new ConfigBuilder()->add_controller(ArticlesController::class)->build();
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
        $config = new ConfigBuilder()->add_controller(ArticlesController::class)->build();
        $actions = $config->controllers[ArticlesController::class]->actions;

        $this->assertEquals(new ActionDefinition('legacy'), $actions['articles:old']);
        $this->assertEquals(new ActionDefinition('legacy'), $actions['articles:older']);
        $this->assertSame(ArticlesController::class, $config->controller_by_action['articles:old']);
        $this->assertSame(ArticlesController::class, $config->controller_by_action['articles:older']);
    }

    public function test_prefix_from_attribute(): void
    {
        $config = new ConfigBuilder()->add_controller(Cart\PhotosController::class)->build();

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
        $config = new ConfigBuilder()->add_controller(UserPostsController::class)->build();

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
            ->add_controller(
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
            ->add_controller(
                PlainController::class,
                fn(RouteBuilder $r) => $r
                    ->get('/:id', 'show')
                    ->prefix(pattern: '/cart', action: 'cart')
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
            ->add_controller(
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
        $config = new ConfigBuilder()->add_controller(PhotosController::class)->build();

        $routes = iterator_to_array($config->routes);

        $this->assertEqualsCanonicalizing(
            array_keys($config->controller_by_action),
            array_map(fn(Route $route) => $route->action, $routes)
        );
    }

    public function test_multiple_controllers(): void
    {
        $config = new ConfigBuilder()
            ->add_controller(PhotosController::class)
            ->add_controller(Cart\PhotosController::class)
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
            ->add_controller(PhotosController::class)
            ->add_controller(ArticlesController::class)
            ->add_controller(Cart\PhotosController::class)
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

        new ConfigBuilder()->add_controller($controller, $route_builder);
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
                . "define a prefix with a pattern using #[Prefix] or RouteBuilder::prefix()."
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
                fn(RouteBuilder $r) => $r->prefix(pattern: '/other'),
                "`" . Cart\PhotosController::class . "` defines its prefix with both the attribute #[Prefix] "
                . "and RouteBuilder::prefix()."
            ],

        ];
    }

    public function test_route_builder_prefix_is_validated(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigBuilder()->add_controller(PlainController::class, fn(RouteBuilder $r) => $r->prefix());
    }

    public function test_action_defined_by_another_controller(): void
    {
        $builder = new ConfigBuilder()->add_controller(PhotosController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The action `photos:show` of `" . ConflictingActionController::class
            . "::show()` is already defined by `" . PhotosController::class . "`."
        );

        $builder->add_controller(ConflictingActionController::class);
    }

    public function test_id_defined_by_two_controllers(): void
    {
        $builder = new ConfigBuilder()->add_controller(FirstIdController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The id `first-route` of `" . SecondIdController::class . "::show()` is already used by `"
            . FirstIdController::class . "::show()`."
        );

        $builder->add_controller(SecondIdController::class);
    }

    public function test_id_defined_by_two_methods(): void
    {
        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The id `same` of `" . SameIdController::class . "::b()` is already used by `"
            . SameIdController::class . "::a()`."
        );

        new ConfigBuilder()->add_controller(SameIdController::class);
    }

    public function test_id_is_the_action_of_another_route(): void
    {
        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The id `id-action:b` of `" . IdIsActionController::class . "::a()` is also the action of `"
            . IdIsActionController::class . "::b()`."
        );

        new ConfigBuilder()->add_controller(IdIsActionController::class);
    }

    public function test_id_is_an_action_of_another_controller(): void
    {
        $builder = new ConfigBuilder()->add_controller(FirstIdController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The id `first-ids:show` of `" . IdOfAnActionController::class . "::show()` is also an action of `"
            . FirstIdController::class . "`."
        );

        $builder->add_controller(IdOfAnActionController::class);
    }

    public function test_action_is_the_id_of_another_controller(): void
    {
        $builder = new ConfigBuilder()->add_controller(FirstIdController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage(
            "The action `first-route` of `" . ActionOfAnIdController::class . "::show()` is also the id of `"
            . FirstIdController::class . "::show()`."
        );

        $builder->add_controller(ActionOfAnIdController::class);
    }

    public function test_id_can_be_the_action_of_its_own_route(): void
    {
        $config = new ConfigBuilder()->add_controller(OwnActionIdController::class)->build();

        $this->assertSame('own:action', $config->routes->route_for_predicate(new ById('own:action'))?->id);
    }

    public function test_ids_are_not_registered_when_a_controller_is_not_valid(): void
    {
        $builder = new ConfigBuilder();

        try {
            $builder->add_controller(SameIdController::class);
            $this->fail("Expected exception");
        } catch (ConfigNotValid) {
            // expected
        }

        $config = $builder->add_controller(FirstIdController::class)->build();

        $this->assertSame([ FirstIdController::class ], array_keys($config->controllers));
        $this->assertNotNull($config->routes->route_for_predicate(new ById('first-route')));
    }

    public function test_controller_can_only_be_added_once(): void
    {
        $builder = new ConfigBuilder()->add_controller(PhotosController::class);

        $this->expectException(ConfigNotValid::class);
        $this->expectExceptionMessage("`" . PhotosController::class . "` is already added.");

        $builder->add_controller(PhotosController::class);
    }

    public function test_nothing_is_registered_when_a_controller_is_not_valid(): void
    {
        $builder = new ConfigBuilder();

        try {
            $builder->add_controller(MissingPlaceholderController::class);
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
            $builder->add_controller(
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
            $builder->add_controller(PlainController::class, fn(RouteBuilder $r) => $r->get('/', 'nope'));
        } catch (ConfigNotValid) {
            // expected
        }

        $config = $builder
            ->add_controller(PlainController::class, fn(RouteBuilder $r) => $r->get('/plain', 'list'))
            ->build();

        $this->assertArrayHasKey('plains:list', $config->controller_by_action);
    }

    public function test_discover_controllers(): void
    {
        $config = new ConfigBuilder()->discover_controllers()->build();

        $this->assertEqualsCanonicalizing(
            [ InvoicesController::class, ReportsController::class ],
            array_keys($config->controllers)
        );
        $this->assertSame(InvoicesController::class, $config->controller_by_action['invoices:list']);
        $this->assertSame(InvoicesController::class, $config->controller_by_action['invoices:create']);
        $this->assertSame(ReportsController::class, $config->controller_by_action['admin:reports:show']);
    }

    public function test_discover_controllers_skips_the_controllers_already_added(): void
    {
        $config = new ConfigBuilder()
            ->add_controller(
                InvoicesController::class,
                fn(RouteBuilder $r) => $r->prefix(pattern: '/billing', action: 'billing')
            )
            ->discover_controllers()
            ->build();

        $this->assertSame(InvoicesController::class, $config->controller_by_action['billing:invoices:list']);
        $this->assertArrayNotHasKey('invoices:list', $config->controller_by_action);
        $this->assertArrayHasKey('admin:reports:show', $config->controller_by_action);
    }
}
