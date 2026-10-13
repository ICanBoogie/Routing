# Creating routes

There are few ways to define routes. Routes are defined by the controllers that respond to their
actions, and you can build the [Route][] instances yourself. The route collector is obsolete.
Whatever you choose you will end up with a variant of [RouteProvider][].

## Creating routes with controllers

A route exists because a controller has an action that answers it. The routes of a controller are
defined by attributes on its methods, or by a `RouteBuilder`, and collected by `ConfigBuilder`.

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Post;

final class PhotosController
{
    #[Get('/photos/:id')]
    public function show(int $id): Response
    {
        // ...
    }

    #[Post('/photos')]
    public function create(): Response
    {
        // ...
    }
}

$config = new ConfigBuilder()
    ->add_controller(PhotosController::class)
    ->add_controller(ContactController::class, fn(RouteBuilder $r) => $r
        ->get('/contact.html', 'new')
        ->post('/contact.html', 'create'))
    ->build();

$config->routes; // a RouteProvider
```

The action of a route, e.g. `photos:show`, is inferred from the name of the controller and the name of
the method, unless the route specifies one. See
[Stateless controllers](adr/0001-stateless-controllers.md) for the design.

## Creating routes using the collector

> **Warning:** The route collector is obsolete. A route should exist because a controller defines it,
> see [Creating routes with controllers](#creating-routes-with-controllers).

The route collector offers a convenient fluent interface to define your routes.

```php
<?php

namespace ICanBoogie\Routing;

$routes = new RouteCollector()
    ->route('/', 'page:home')
    ->get('/contact.html', 'contact:new')
    ->post('/contact.html', 'contact:create')
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

use ICanBoogie\Routing\RouteProvider\Immutable;

$routes = new Immutable([
    new Route('/', 'page:home'),
    new Route('/about.html', 'page:about'),
]);
```

## Creating resource routes using `RouteMaker`

> **Warning:** `RouteMaker` is obsolete. It makes routes that a controller might not implement,
> whereas a route should exist because a controller defines it, see
> [Creating routes with controllers](#creating-routes-with-controllers).

Given a resource name, the `RouteMaker::resource()` method makes the various routes required to
handle a resource. A [RouteMaker\Options][] instance can be specified to filter the routes to create,
specify the name of the _key_ property and/or its regex constraint, or name routes.

The following example demonstrates how to create routes for an _article_ resource:

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\RouteMaker as Make;
use ICanBoogie\Routing\RouteMaker\Options;

// create all resource routes
$routes = Make::resource('articles');

// only create the _list_ definition
$routes = Make::resource('articles', new Options(only: [ Make::ACTION_LIST ]));

// only create the _list_ and _show_ definitions
$routes = Make::resource('articles', new Options(only: [ Make::ACTION_LIST, Make::ACTION_SHOW ]));

// create definitions except _delete_
$routes = Make::resource('articles', new Options(except: [ Make::ACTION_DELETE ]));

// create definitions except _update_ and _delete_
$routes = Make::resource('articles', new Options(except: [ Make::ACTION_UPDATE, Make::ACTION_DELETE ]));

// specify _key_ property name and its regex constraint
$routes = Make::resource('articles', new Options(id_name: 'uuid', id_regex: '{:uuid:}'));

// specify the action of the _create_ definition
$routes = Make::resource('articles', new Options(as: [ Make::ACTION_CREATE => 'articles:build' ]));

// specify the identifier of the _list_ definition
$routes = Make::resource('articles', new Options(ids: [ Make::ACTION_LIST => 'articles-list' ]));
```

The same options are accepted by `RouteCollector::resource()`.

> **Note:** It is not required to define all the resource actions, only define the one you actually need.



[Route]: ../lib/Route.php
[RouteProvider]: ../lib/RouteProvider.php
[RouteProvider\Mutable]: ../lib/RouteProvider/Mutable.php
[RouteProvider\Immutable]: ../lib/RouteProvider/Immutable.php
[RouteMaker\Options]: ../lib/RouteMaker/Options.php
