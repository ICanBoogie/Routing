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

    public function test_name_replaces_the_inferred_one(): void
    {
        $this->assertSame('contact:show', ActionResolver::resolve('App\ContactsController', 'show', 'contact'));
        $this->assertSame('Contact_Us:show', ActionResolver::resolve('App\ContactsController', 'show', 'Contact_Us'));
    }

    /**
     * @return array<string, array{ class-string, string, string }>
     */
    public static function provide_resolve(): array
    {
        return [

            'controller suffix is stripped' => [ 'App\PhotosController', 'show', 'photos:show' ],
            'namespace is stripped' => [ 'App\Presentation\Frontend\Cart\PhotosController', 'list', 'photos:list' ],
            'not pluralized' => [ 'App\ContactController', 'show', 'contact:show' ],
            'plural is kept' => [ 'App\ContactsController', 'show', 'contacts:show' ],
            'camel case is hyphenated' => [ 'App\PostCategoriesController', 'list', 'post-categories:list' ],
            'acronym is hyphenated' => [ 'App\HTTPServerController', 'show', 'http-server:show' ],
            'digits' => [ 'App\Oauth2ClientsController', 'list', 'oauth2-clients:list' ],
            'no suffix' => [ 'App\Photos', 'show', 'photos:show' ],
            'no namespace' => [ 'ArticlesController', 'show', 'articles:show' ],
            'method is used as is' => [ 'App\PhotosController', 'get_show', 'photos:get_show' ],
            'method case is kept' => [ 'App\PhotosController', 'showAll', 'photos:showAll' ],
            'only the suffix' => [ 'App\Controller', 'index', 'controller:index' ],

        ];
    }
}
