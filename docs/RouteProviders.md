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

## Precedence

When several routes match a URI, the provider decides which one is returned:

- `Mutable` and `Immutable` return the **first route in declaration order**. Declare
  `/articles/new` before `/articles/<id>`, or the latter wins.
- `Memoize`, `MemoizeByUri` and `Compiled` return **static routes first**, then dynamic routes with
  more static leading path segments first (`/articles/<id>/edit` before `/articles/<id>`), and
  finally declaration order for routes of equal weight.

The same route table can therefore give different results with a plain provider and with a
memoized one. `Mutable` and `Immutable` are conveniences to build and iterate over a table; use
`Memoize` or `Compiled` to serve requests, and avoid patterns that overlap, because routes of equal
weight (e.g. `/articles/<id:\d+>` and `/articles/<slug>`) are only separated by declaration order.

## Performance

Matching a URI against a plain provider is a linear scan with a regex per dynamic route.
`RouteProvider\Memoize` decorates a provider and speeds up the built-in predicates:

```php
<?php

use ICanBoogie\Routing\RouteProvider\Memoize;

/* @var \ICanBoogie\Routing\RouteProvider $routes */

$routes = new Memoize($routes);
```

- `ByUri`: static routes are found with a single array lookup and tried first, then dynamic routes are
  tried by chunks of about 30 routes, each chunk matched with a single regular expression, routes
  with more static leading path segments first.
- `ById`, `ByAction`, `ByIdOrAction`: lookups are indexed.
- Custom callable predicates fall through to the decorated provider.

### Compiling routes for production

`Memoize` computes its indexes in memory, which is lost when the process ends. With PHP-FPM, that's
every request. On top of that, loading routes from a file means creating every `Route` object.
Together, that is the dominant cost of routing: tens of microseconds for 100 routes, most of a
millisecond for 1,000, whereas finding a route takes about a microsecond.

`RouteProvider\Compiled` avoids both. The routes and their indexes are compiled once into
constant arrays, written to a PHP file, and loaded through OPcache, which shares them between
requests. `Route` instances are only created for the routes that are needed.

```php
<?php

use ICanBoogie\Routing\RouteProvider\Compiled;

/* @var \ICanBoogie\Routing\RouteProvider $routes */
/* @var string $file */

// At build time, or during deployment
$tmp = "$file." . uniqid('', true);
file_put_contents($tmp, Compiled::dump($routes));
rename($tmp, $file); // atomic, a concurrent request never reads a partial file

// At runtime
$routes = new Compiled(require $file);
```

`Compiled` optimizes the same predicates as `Memoize`, and is iterable. Route extensions are exported
with `var_export()`, they must implement `__set_state()`. The file has to be regenerated whenever
routes change.

Make sure OPcache is enabled. PHP-FPM also benefits from `pcre.jit=1`, which is the default.

Both `Mutable` and `Immutable` also implement `__set_state()`, so they can be exported with
`var_export()` and cached as a PHP file. This is simpler, but the objects are created on each
request and `Memoize` has to build its indexes again.

## Writing a custom predicate

A predicate is any `callable(Route): bool`:

```php
use ICanBoogie\Routing\Route;

$route = $routes->route_for_predicate(
    fn(Route $route) => str_starts_with($route->action, 'admin:')
);
```


[RouteProvider]: ../lib/RouteProvider.php
[RouteProvider\ById]: ../lib/RouteProvider/ById.php
[RouteProvider\ByAction]: ../lib/RouteProvider/ByAction.php
[RouteProvider\ByIdOrAction]: ../lib/RouteProvider/ByIdOrAction.php
[RouteProvider\ByUri]: ../lib/RouteProvider/ByUri.php
