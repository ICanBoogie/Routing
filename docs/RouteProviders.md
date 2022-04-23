# Route Providers

Route providers are used to find the route that matches a predicate. Several route provider
implementations are available, for flexibility and performance. And several predicate implementation
are available to match routes by an action or a URI. Simple route providers are often decorated with
more sophisticated ones that can improve performance.

## Predicates

Route providers implement the [RouteProvider][] interface. The `route_for_predicate()` method is used to find a route
that matches a predicate. A predicate can be as simple as a callable. The following predicates come built-in:

- [RouteProvider\ById][]: Matches a route against an identifier.
- [RouteProvider\ByAction][]: Matches a route against an action.
- [RouteProvider\ByUri][]: Matches a route against a URI and an optional method. Path parameters and query parameters
  are captured in the predicate.

The following example demonstrates how to find route matching a URL and method, using the `ByUri` predicate:

```php
<?php

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\RouteProvider\ByUri;

/* @var ICanBoogie\Routing\RouteProvider $route_provider */

$route = $route_provider->route_for_predicate($predicate = new ByUri('/?singer=madonna'));
echo $route->action; // "home"
var_dump($predicate->query_params); // [ 'singer' => 'madonna' ]

$route = $route_provider->route_for_predicate($predicate = new ByUri('/articles/123', RequestMethod::METHOD_DELETE));
echo $route->action; // "articles:show"
var_dump($predicate->path_params); // [ 'nid' => 123 ]
```
