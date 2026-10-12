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

Providers are iterable, e.g. to list all routes:

```php
foreach ($route_provider as $route) {
    echo "$route->pattern → $route->action\n";
}
```

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
