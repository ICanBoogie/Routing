# Creating routes

There are few ways to define routes. You can build the [Route][] instances yourself or use the route
collector. Whatever you choose you will end up with a variant of [RouteProvider][].

## Creating routes using the collector

The route collector offers a convenient fluent interface to define your routes.

```php
<?php

namespace ICanBoogie\Routing;

$routes = (new RouteCollector())
    ->route('/', 'page:home')
    ->get('/contact.html', 'contact:new')
    ->post('/contact.html', 'contact:create')
    ->resource('photos')
    ->collect();
```

## Creating routes by hand

You can define your routes by hand, and store them in an instance of either
[RouteProvider\Mutable][] or [RouteProvider\Immutable][], depending on whether you want to be able
to add routes later or not.

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\RouteProvider\Mutable;

$routes = new Mutable();
$routes->add_routes(
    new Route('/', 'page:home'),
    new Route('/about.html', 'page:about'),
);

use ICanBoogie\Routing\RouteProvider\Mutable;

$routes = new Imutable([
    new Route('/', 'page:home'),
    new Route('/about.html', 'page:about'),
]);
```

## Creating resource routes using `RouteMaker`

Given a resource name and a controller, the `RouteMaker::resource()` method makes the various
routes required to handle a resource. Options can be specified to filter the routes to create,
specify the name of the _key_ property and/or it's regex constraint, or name routes.

The following example demonstrates how to create routes for an _article_ resource:

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\RouteMaker as Make;

// create all resource routes
$routes = Make::resource('articles');

// only create the _list_ definition
$routes = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ONLY => Make::ACTION_LIST

]);

// only create the _list_ and _show_ definitions
$routes = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ONLY => [ Make::ACTION_LIST, Make::ACTION_SHOW ]

]);

// create definitions except _destroy_
$routes = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_EXCEPT => Make::ACTION_DELETE

]);

// create definitions except _updated_ and _destroy_
$routes = Make::resource('articles', PhotosController::class, [

    Make::OPTION_EXCEPT => [ Make::ACTION_UPDATE, Make::ACTION_DELETE ]

]);

// specify _key_ property name and its regex constraint
$routes = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ID_NAME => 'uuid',
    Make::OPTION_ID_REGEX => '{:uuid:}'

]);

// specify the identifier of the _create_ definition
$routes = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_AS => [

        Make::ACTION_CREATE => 'articles:build'

    ]

]);
```

> **Note:** It is not required to define all the resource actions, only define the one you actually need.



[Route]: ../lib/Route.php
[RouteProvider]: ../lib/RouteProvider.php
[RouteProvider\Mutable]: ../lib/RouteProvider/Mutable.php
[RouteProvider\Immutable]: ../lib/RouteProvider/Immutable.php
