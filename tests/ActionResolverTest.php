<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\ActionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActionResolver::class)]
final class ActionResolverTest extends TestCase
{
    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    #[DataProvider('provide_resolve')]
    public function test_resolve(string $class, string $method, string $expected): void
    {
        $this->assertSame($expected, ActionResolver::resolve($class, $method));
    }

    /**
     * @return array<string, array{ class-string, string, string }>
     */
    public static function provide_resolve(): array
    {
        return [

            'controller suffix is stripped' => [ 'App\PhotosController', 'show', 'photos:show' ],
            'namespace is stripped' => [ 'App\Presentation\Frontend\Cart\PhotosController', 'list', 'photos:list' ],
            'singular is pluralized' => [ 'App\ArticleController', 'show', 'articles:show' ],
            'camel case is hyphenated' => [ 'App\PostCategoryController', 'list', 'post-categories:list' ],
            'no suffix' => [ 'App\Photos', 'show', 'photos:show' ],
            'no namespace' => [ 'ArticleController', 'show', 'articles:show' ],
            'method is used as is' => [ 'App\PhotosController', 'get_show', 'photos:get_show' ],
            'method case is kept' => [ 'App\PhotosController', 'showAll', 'photos:showAll' ],
            'only the suffix' => [ 'App\Controller', 'index', 'controllers:index' ],

        ];
    }
}
