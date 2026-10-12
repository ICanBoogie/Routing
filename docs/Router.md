# Using the Router

[Router][] is the shortest path from a pattern to a handler. Each method registers a [Route][] with
a generated action (`__anonymous_action_N`) and a responder that calls your closure.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ActionResponderProvider;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\Router;

$routes = new RouteProvider\Mutable();
$responders = new ActionResponderProvider\Mutable();

$router = new Router($routes, $responders);
$router
    ->get('/', fn() => new Response('Home'))
    ->post('/articles', fn(Request $request) => new Response('Created', 201))
    ->delete('/articles/<id:\d+>', function (Request $request) {
        $id = $request->path_params['id'];

        return new Response("Deleted $id");
    });
```

Available methods: `any`, `get`, `post`, `put`, `patch`, `delete`, `head`, `options`, `connect`,
`trace`. The closure receives the `Request` and must return a `Response`. Values captured by the
pattern are in `$request->path_params`.

`Router` requires _mutable_ providers (`RouteProvider\Mutable` and `ActionResponderProvider\Mutable`).

> [!TIP]
> Routes created by the Router have anonymous actions, so they cannot be targeted by
> [URL generation](UrlGeneration.md) with a meaningful action. If you need to generate URLs, define
> the route with an action or an identifier using the [route collector](CreatingRoutes.md) and
> register the responder with `ActionResponderProvider\Mutable::add_responder()`.



[Router]: ../lib/Router.php
[Route]: ../lib/Route.php
