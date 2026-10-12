<?php

namespace Test\ICanBoogie\Routing\RouteProvider;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ById;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use ICanBoogie\Routing\RouteProvider\ByUri;
use ICanBoogie\Routing\RouteProvider\Chain;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\Memoize;
use ICanBoogie\Routing\RouteProvider\Mutable;
use PHPUnit\Framework\TestCase;

final class ChainTest extends TestCase
{
    private Route $home;
    private Route $article;
    private Route $about;
    private Route $contact;
    private Mutable $mutable;
    private Chain $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $base = new Immutable([
            $this->home = new Route('/', 'page:home', id: 'home'),
            $this->article = new Route('/articles/<nid:\d+>', 'articles:show', RequestMethod::METHOD_GET),
        ]);

        $this->mutable = new Mutable();
        $this->mutable->add_routes(
            $this->about = new Route('/about.html', 'page:about', id: 'about'),
            $this->contact = new Route('/contact/:name', 'page:contact'),
        );

        $this->chain = new Chain([ $base, $this->mutable ]);
    }

    public function test_finds_a_route_in_the_first_provider(): void
    {
        $this->assertSame($this->home, $this->chain->route_for_predicate(new ByAction('page:home')));
        $this->assertSame($this->home, $this->chain->route_for_predicate(new ById('home')));
    }

    public function test_finds_a_route_in_the_following_providers(): void
    {
        $this->assertSame($this->about, $this->chain->route_for_predicate(new ByAction('page:about')));
        $this->assertSame($this->about, $this->chain->route_for_predicate(new ById('about')));
        $this->assertSame($this->contact, $this->chain->route_for_predicate(new ByIdOrAction('page:contact')));
    }

    public function test_returns_null_when_no_provider_has_the_route(): void
    {
        $this->assertNull($this->chain->route_for_predicate(new ByAction('nope')));
        $this->assertNull($this->chain->route_for_predicate(new ByUri('/nothing/here')));
        $this->assertNull($this->chain->route_for_predicate(fn(Route $route): bool => false));
    }

    public function test_the_first_provider_wins(): void
    {
        $overridden = new Route('/', 'page:home:overridden', id: 'home');
        $this->mutable->add_routes($overridden);

        $this->assertSame($this->home, $this->chain->route_for_predicate(new ById('home')));
        $this->assertSame($this->home, $this->chain->route_for_predicate(new ByUri('/')));
        $this->assertSame($overridden, $this->chain->route_for_predicate(new ByAction('page:home:overridden')));
    }

    public function test_a_mutable_provider_first_overrides_the_routes_of_the_base(): void
    {
        $overridden = new Route('/', 'page:home:overridden', id: 'home');
        $this->mutable->add_routes($overridden);

        $chain = new Chain([ $this->mutable, new Immutable([ $this->home, $this->article ]) ]);

        $this->assertSame($overridden, $chain->route_for_predicate(new ById('home')));
        $this->assertSame($overridden, $chain->route_for_predicate(new ByUri('/')));
        $this->assertSame($this->article, $chain->route_for_predicate(new ByAction('articles:show')));
        $this->assertSame(
            [ $this->about, $this->contact, $overridden, $this->home, $this->article ],
            [ ...$chain ]
        );
    }

    public function test_by_uri_captures_params_from_the_provider_that_matches(): void
    {
        $predicate = new ByUri('/articles/123?q=1&nid=2', RequestMethod::METHOD_GET);

        $this->assertSame($this->article, $this->chain->route_for_predicate($predicate));
        $this->assertSame([ 'nid' => '123' ], $predicate->path_params);
        $this->assertSame([ 'q' => '1' ], $predicate->query_params);

        $predicate = new ByUri('/contact/olivier?q=1', RequestMethod::METHOD_GET);

        $this->assertSame($this->contact, $this->chain->route_for_predicate($predicate));
        $this->assertSame([ 'name' => 'olivier' ], $predicate->path_params);
        $this->assertSame([ 'q' => '1' ], $predicate->query_params);
    }

    public function test_by_uri_without_a_match_leaves_the_predicate_untouched(): void
    {
        $predicate = new ByUri('/nothing/here?q=1');

        $this->assertNull($this->chain->route_for_predicate($predicate));
        $this->assertSame([], $predicate->path_params);
        $this->assertSame([ 'q' => '1' ], $predicate->query_params);
    }

    public function test_iterates_over_the_routes_of_each_provider_in_order(): void
    {
        $this->assertSame(
            [ $this->home, $this->article, $this->about, $this->contact ],
            [ ...$this->chain ]
        );
    }

    public function test_iterating_an_empty_chain(): void
    {
        $chain = new Chain([]);

        $this->assertSame([], [ ...$chain ]);
        $this->assertNull($chain->route_for_predicate(new ById('home')));
    }

    /**
     * An immutable base can be decorated, its routes are set in stone, and routes added to the
     * mutable provider are still found.
     */
    public function test_routes_added_to_the_mutable_provider_are_found_after_the_base_is_memoized(): void
    {
        $base = new Memoize(new Immutable([ $this->home, $this->article ]));
        $mutable = new Mutable();
        $chain = new Chain([ $base, $mutable ]);

        // Warms up the indexes of the memoized base.
        $this->assertSame($this->home, $chain->route_for_predicate(new ById('home')));
        $this->assertNull($chain->route_for_predicate(new ByUri('/new')));

        $mutable->add_routes($new = new Route('/new', 'page:new', id: 'new'));

        $this->assertSame($new, $chain->route_for_predicate(new ByUri('/new')));
        $this->assertSame($new, $chain->route_for_predicate(new ById('new')));
        $this->assertSame($this->home, $chain->route_for_predicate(new ById('home')));
        $this->assertSame([ $this->home, $this->article, $new ], [ ...$chain ]);
    }
}
