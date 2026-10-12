# Using the Router

[Router][] is the shortest path from a pattern to a handler. Each method registers a [Route][] with
an action (generated unless you provide one) and a responder that calls your closure.

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

## Naming actions

By default, the action of a route is generated (`__anonymous_action_N`), which is unstable and
cannot be targeted by [URL generation](UrlGeneration.md). Pass an `action` to name it; it takes
precedence over the generated one:

```php
<?php

use ICanBoogie\HTTP\Response;

$router->get('/articles', fn() => new Response('Articles'), action: 'articles:list');

$url_generator->generate_url('articles:list'); // /articles
```

The action is also the key of the responder in `ActionResponderProvider\Mutable`, use a unique one
per route.

[Router]: ../lib/Router.php
[Route]: ../lib/Route.php
