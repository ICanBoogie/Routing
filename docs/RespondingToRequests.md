# Responding to a request

A request can be dispatched to a matching Responder provided a route matches the request URI and
method.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\RequestOptions;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;

$routes = new RouteProvider\Immutable([

    new Route('/articles/<id:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE)

]);

$request = Request::from([

    RequestOptions::OPTION_URI => "/articles/123",
    RequestOptions::OPTION_METHOD => RequestMethod::METHOD_DELETE,

]);

/* @var Responder $responder */

$response = $responder->respond($request);
```

See [Getting started](GettingStarted.md) for the complete wiring, including how `$responder` is
built from a route provider and an action responder provider.
