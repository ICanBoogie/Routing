# Getting started

This guide wires everything together: define routes, tell the package what should respond to each
route, and dispatch a request. Each step links to a page with the details.

```shell
composer require icanboogie/routing
```

## How a request is handled

```
Request ──► RequestResponderProvider ──► Responder ──► Response
              │            │
              │            └─ ActionResponderProvider   "which responder handles this action?"
              └─ RouteProvider                          "which route matches this URI and method?"
```

1. A [RouteProvider][] finds the [Route][] matching the request URI and method.
2. The route's _action_ (e.g. `articles:show`) is handed to an [ActionResponderProvider][], which
   returns the `Responder` in charge of that action.
3. The responder produces the `Response`.

[RequestResponderProvider][] performs these steps. It returns `null` when no route matches (the
HTTP stack then typically answers with a 404), throws `MethodNotAllowed` when the URI matches but
not the method, and throws `NoResponder` when a route has no responder.

## Step 1: define routes and responders

The quickest way is the [Router][], which maps a pattern to a closure and registers both the route
and the responder:

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
    ->get('/', fn(Request $request) => new Response('Hello world!'))
    ->get('/articles/<id:\d+>', fn(Request $request) => new Response("Article {$request->path_params['id']}"));
```

See [Using the Router](Router.md). When your application grows, define routes with the
[route collector](CreatingRoutes.md) and use [controllers](Controllers.md) as responders.

## Step 2: dispatch the request

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder\DelegateToProvider;
use ICanBoogie\Routing\RequestResponderProvider;

/* @var \ICanBoogie\Routing\RouteProvider $routes */
/* @var \ICanBoogie\Routing\ActionResponderProvider $responders */

$application = new DelegateToProvider(new RequestResponderProvider($routes, $responders));

$request = Request::from($_SERVER);
$response = $application->respond($request);
$response();
```

During dispatch the matched route is available as `$request->context->get(Route::class)` and the
values captured from the path as `$request->path_params`.

## Step 3: generate URLs

Routes work both ways. Given an action or route identifier, [UrlGenerator][] builds the URL:

```php
$url_generator->generate_url('articles:show', [ 'id' => 123 ]); // "/articles/123"
```

See [URL generation](UrlGeneration.md).

## Where to go next

| I want to…                                      | Read                                          |
|-------------------------------------------------|-----------------------------------------------|
| Understand the pattern syntax                   | [Route patterns](RoutePatterns.md)            |
| Define many routes, or REST resources           | [Defining routes](CreatingRoutes.md)          |
| Organize handlers in classes                    | [Controllers](Controllers.md)                 |
| Resolve responders from a DI container          | [Action responders](ActionResponders.md)      |
| Run code around every route (cache, redirects)  | [Middleware and events](Middleware.md)        |
| Make route lookup faster in production          | [Route providers](RouteProviders.md)          |
| Understand an error                             | [Exceptions](Exceptions.md)                   |



[Route]: ../lib/Route.php
[RouteProvider]: ../lib/RouteProvider.php
[ActionResponderProvider]: ../lib/ActionResponderProvider.php
[RequestResponderProvider]: ../lib/RequestResponderProvider.php
[Router]: ../lib/Router.php
[UrlGenerator]: ../lib/UrlGenerator.php
