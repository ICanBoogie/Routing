# Route Providers

Route providers are used to find the route that matches a predicate. Several route provider
implementations are available, for flexibility and performance. And several predicate implementation
are available to match routes by an action or a URI. Simple route providers are often decorated with
more sophisticated ones that can improve performance.

## Predicates

Route providers implement the [RouteProvider][] interface. The `route_for_predicate()` method is
used to find a route that matches a predicate. A predicate can be as simple as a callable. The
following predicates come built-in:

- [RouteProvider\ById][]: Matches a route against an identifier.
- [RouteProvider\ByAction][]: Matches a route against an action.
- [RouteProvider\ByIdOrAction][]: Matches a route against an identifier or an action, whichever
  comes first.
- [RouteProvider\ByUri][]: Matches a route against a URI and an optional method. Path parameters and
  query parameters are captured in the predicate.

The following example demonstrates how to find route matching a URL and method, using the `ByUri`
predicate:

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\RouteProvider\ByUri;

/* @var RouteProvider $route_provider */

$route = $route_provider->route_for_predicate($predicate = new ByUri('/?singer=madonna'));
echo $route->action; // "home"
var_dump($predicate->query_params); // [ 'singer' => 'madonna' ]

$route = $route_provider->route_for_predicate($predicate = new ByUri('/articles/123', RequestMethod::METHOD_DELETE));
echo $route->action; // "articles:show"
var_dump($predicate->path_params); // [ 'nid' => 123 ]
```

## Implementations

- `RouteProvider\Mutable`: A list of routes that can grow with `add_routes()`.
- `RouteProvider\Immutable`: A fixed list of routes. This is what `RouteCollector::collect()` returns.
- `RouteProvider\Chain`: Asks each provider of a list in turn, the first route found wins. See
  [Recommended setup](#recommended-setup).

Providers are iterable, e.g. to list all routes:

```php
foreach ($route_provider as $route) {
    echo "$route->pattern → $route->action\n";
}
```

## Recommended setup

`Immutable` is the base of an application: it holds the routes that are set in stone. Because they can't
change, it is safe to decorate it with `Memoize`, whose indexes would be stale as soon as a route is
added. `Mutable` is for the bit of freedom you need at runtime (routes registered by a plugin, or in a
test), where a linear scan is not an issue. A `Chain` puts them together. The order is yours to choose, see below.

```php
<?php

use ICanBoogie\Routing\RouteProvider\Chain;
use ICanBoogie\Routing\RouteProvider\Memoize;
use ICanBoogie\Routing\RouteProvider\Mutable;
use ICanBoogie\Routing\Route;

/* @var \ICanBoogie\Routing\RouteProvider\Immutable $immutable */

$mutable = new Mutable();
// Mutable first if you want to override routes; it has a small cost if you're reasonable
// Mutable second if you only want to add new routes
$routes = new Chain([ new Memoize($immutable), $mutable ]);

// Later, at runtime
$mutable->add_routes(new Route('/plugin/status', 'plugin:status'));
```

- **The first provider wins.** If both providers have a route for the same URI, identifier or action,
  only the route of the first one is found. With the mutable provider second, as in the example, it can
  only add new routes. With the mutable provider first, its routes override those of the base, at the
  price of a linear scan of the mutable provider before every lookup of the base.
- **Iteration** lists the routes of the providers in the order of the chain.
- **Pass an array of providers.** The providers are iterated for each lookup, an iterator that can only
  be traversed once (such as a generator) would be exhausted by the first one.
- **Avoid decorating a `Mutable`** with `Memoize`, unless you are done adding routes: routes added
  after the first lookup would not be found by the indexes.
- **A `Chain` is not exported.** Export the `Immutable` base, which is the expensive part, and
  create the `Chain` when the application starts.

## Performance

Matching a URI against a plain provider is a linear scan with a regex per dynamic route.
`RouteProvider\Memoize` decorates a provider and speeds up the built-in predicates:

```php
<?php

use ICanBoogie\Routing\RouteProvider\Memoize;

/* @var \ICanBoogie\Routing\RouteProvider $routes */

$routes = new Memoize($routes);
```

- `ByUri`: static routes are compared as strings and tried first, then dynamic routes are tried,
  routes with more static leading path segments first.
- `ById`, `ByAction`, `ByIdOrAction`: lookups are indexed.
- Custom callable predicates fall through to the decorated provider.

Both `Mutable` and `Immutable` implement `__set_state()`, so a provider can be exported with
`var_export()` and cached as a PHP file, which makes it a good candidate for build-time caching.

## Writing a custom predicate

A predicate is any `callable(Route): bool`:

```php
$route = $routes->route_for_predicate(
    fn(Route $route) => str_starts_with($route->action, 'admin:')
);
```


[RouteProvider]: ../lib/RouteProvider.php
[RouteProvider\ById]: ../lib/RouteProvider/ById.php
[RouteProvider\ByAction]: ../lib/RouteProvider/ByAction.php
[RouteProvider\ByIdOrAction]: ../lib/RouteProvider/ByIdOrAction.php
[RouteProvider\ByUri]: ../lib/RouteProvider/ByUri.php
