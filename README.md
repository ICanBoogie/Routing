# Routing

[![Release](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Build Status](https://img.shields.io/github/workflow/status/ICanBoogie/Routing/test)](https://github.com/ICanBoogie/Routing/actions?query=workflow%3Atest)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Routing.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Routing)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Routing.svg)](https://coveralls.io/r/ICanBoogie/Routing)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A request is mapped
to a route, which in turn gets dispatched to a controller, and possibly an action. If the
process is successful a response is returned. Events are fired during the process to allow
hooks to alter the request, the route, the controller, or the response.





## Dispatching a request

Routes are dispatched by a [RouteDispatcher][] instance, which may be used on its own or
as a _domain dispatcher_ by a [RequestDispatcher][] instance.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\RouteDefinition;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\RouteCollection;

$routes = new RouteCollection([

    'articles:delete' => [

        RouteDefinition::PATTERN => '/articles/<id:\d+>',
        RouteDefinition::CONTROLLER => ArticlesController::class,
        RouteDefinition::ACTION => 'delete',
        RouteDefinition::VIA => Request::METHOD_DELETE

    ]

]);

$request = Request::from([

    Request::OPTION_URI => "/articles/123",
    Request::OPTION_IS_DELETE => true

]);

$dispatcher = new RouteDispatcher($routes);
$response = $dispatcher($request);
$response();
```





### Before a route is dispatched

Before a route is dispatched the `ICanBoogie\Routing\RouteDispatcher::dispatch:before` event
of class [RouteDispatcher\BeforeDispatchEvent][] is fired. Event hooks may use this event
to provide a response and thus cancel the dispatching.





### A route is dispatched

The `ICanBoogie\Routing\RouteDispatcher::dispatch` event of class [RouteDispatcher\DispatchEvent][]
is fired if the route has been dispatched successfully. Event hooks may use this event to
alter the response.





### Rescuing a route

If an exception is raised during dispatching, the `ICanBoogie\Routing\Route::rescue` event
of class [Route\RescueEvent][] is fired. Event hooks may use this event to rescue the route and
provide a response, or replace the exception that will be thrown if the rescue fails.





## Route definitions

A route definition is an array, which may be created with the following keys:

- `RouteDefinition::PATTERN`: The pattern of the URL.
- `RouteDefinition::CONTROLLER`: The controller class or a callable.
- `RouteDefinition::ACTION`: An optional action of the controller.
- `RouteDefinition::ID`: The identifier of the route.
- `RouteDefinition::VIA`: If the route needs to respond to one or more HTTP methods, e.g.
`Request::METHOD_GET` or `[ Request::METHOD_PUT, Request::METHOD_PATCH ]`.
Defaults: `Request::METHOD_GET`.
- `RouteDefinition::LOCATION`: To redirect the route to another location.
- `RouteDefinition::CONSTRUCTOR`: If the route should be instantiated from a class other than
[Route][].

A route definition is considered valid when the `RouteDefinition::PATTERN` parameter is defined
along one of `RouteDefinition::CONTROLLER` or `RouteDefinition::LOCATION`. [InvalidPattern][] is
thrown if `RouteDefinition::PATTERN` is missing, and [ControllerNotDefined][] is thrown if both
`RouteDefinition::CONTROLLER` and `RouteDefinition::LOCATION` are missing.

> **Note:** You can add any parameter you want to the route definition, they are used to create
> the route instance, which might be useful to provide additional information to a controller.
> Better use a custom route class though.





### Route patterns

A pattern is used to matches a URL with a route. Placeholders may be used to matches multiple URL to a
single route and extract its parameters. Three types of placeholder are available:

- Relaxed placeholder: Only the name of the parameter is specified, it matches anything until
the following part. e.g. `/articles/:id/edit` where `:id` is the placeholder for
the `RouteDefinition::ID` parameter.

- Constrained placeholder: A regular expression is used to matches the parameter value.
e.g. `/articles/<id:\d+>/edit` where `<id:\d+>` is the placeholder for the `id` parameter
which value must matches `/^\d+$/`.

- Anonymous constrained placeholder: Same as the constrained placeholder, except the parameter
has no name but an index e.g. `/articles/<\d+>/edit` where `<\d+>` in a placeholder
which index is 0.

Additionally, the joker character `*`—which can only be used at the end of a pattern—matches
anything. e.g. `/articles/123*` matches `/articles/123` and `/articles/123456` as well.

Finally, constraints RegEx are extended with the following:

- `{:sha1:}`: Matches [SHA-1](https://en.wikipedia.org/wiki/SHA-1) hashes. e.g. `/files/<hash:{:sha1:}>`.
- `{:uuid:}`: Matches [Universally unique identifiers](https://en.wikipedia.org/wiki/Universally_unique_identifier)
(UUID). e.g. `/articles/<uuid:{:uuid:}>/edit`.

You can use them in any combination:

- `/blog/:year-:month-:slug`
- `/blog/<year:\d{4}>-<month:\d{2}>-:slug`
- `/images/<uuid:{:uuid:}>/<size:\d+x|x\d+|\d+x\d+>*`





### Route controller

The `RouteDefinition::CONTROLLER` key specifies the callable to invoke, or the class name of a
callable. An action can be specified with `RouteDefinition::ACTION` and if the callable uses
[ActionTrait][] the call will be mapped automatically to the appropriate method.

Controllers can also be defined as service references when the [icanboogie/service] package
is used.





## Route collections

A [RouteCollection][] instance holds route definitions and is used to create [Route][] instances.
A route dispatcher uses an instance to map a request to a route. A route collection is usually
created with an array of route definitions, which may come from configuration fragments,
[RouteMaker][], or an expertly crafted array. After the route collection is created it may be
modified by using the collection as a array, or by adding routes using one of
the supported HTTP methods. Finally, a collection may be created from another using
the `filter()` method.





### Defining routes using configuration fragments

If the package is bound to [ICanBoogie][] using [icanboogie/bind-routing][], routes can be defined
using `routes` configuration fragments. Refer to [icanboogie/bind-routing][] documentation to
learn more about this feature.

```php
<?php

use ICanBoogie\Routing\RouteCollection;

// …

$routes = new RouteCollection($app->configs['routes']);
# or
$routes = $app->routes;
```





### Defining routes using offsets

Used as an array, routes can be defined by setting/unsetting the offsets of a [RouteCollection][].

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\RouteCollection;

$routes = new RouteCollection;

$routes['articles:index'] = [

    RouteDefinition::PATTERN => '/articles',
    RouteDefinition::CONTROLLER => ArticlesController::class,
    RouteDefinition::ACTION => 'index',
    RouteDefinition::VIA => Request::METHOD_GET

];

unset($routes['articles:index']);
```





### Defining routes using HTTP methods

Routes may be defined using HTTP methods, such as `get` or `delete`.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\RouteDefinition;

$routes = new RouteCollection;
$routes->any('/', function(Request $request) { }, [ RouteDefinition::ID => 'home' ]);
$routes->any('/articles', function(Request $request) { }, [ RouteDefinition::ID => 'articles:index' ]);
$routes->get('/articles/new', function(Request $request) { }, [ RouteDefinition::ID => 'articles:new' ]);
$routes->post('/articles', function(Request $request) { }, [ RouteDefinition::ID => 'articles:create' ]);
$routes->delete('/articles/<nid:\d+>', function(Request $request) { }, [ RouteDefinition::ID => 'articles:delete' ]);
```





### Filtering a route collection

Sometimes you want to work with a subset of a route collection, for instance the routes related to
the admin area of a website. The `filter()` method filters routes using a callable filter and
returns a new [RouteCollection][].

The following example demonstrates how to filter _index_ routes in an "admin" namespace.
You can provide a closure, but it's best to create filter classes that you can extend and reuse:

```php
<?php

class AdminIndexRouteFilter
{
    /**
     * @param array $definition A route definition.
     * @param string $id A route identifier.
     *
     * @return bool
     */
    public function __invoke(array $definition, $id)
    {
        return strpos($id, 'admin:') === 0 && !preg_match('/:index$/', $id);
    }
}

$filtered_routes = $routes->filter(new AdminIndexRouteFilter);
```





## Route providers

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





## Route

A route is represented by a [Route][] instance. It is usually created from a definition array
and contains all the properties of its definition.

```php
<?php

$route = $routes['articles:show'];
echo get_class($route); // ICanBoogie\Routing\Route;
```





## Controllers

Previous examples demonstrated how closures could be used to handle routes. Closures are
perfectly fine when you start building your application, but as soon as it grows you might want
to use controller classes instead to better organize your application. You can map each route to
its [Controller][] class, or use the [ActionTrait][] to group related HTTP
request handling logic into a single controller.





### Controller response

When invoked, the controller should return a result, or `null` if it can't handle the request.
The result of the `action()` method is handled by the `__invoke()` method: if the result is a
[Response][] instance it is returned as is; if the [Response][] instance attached to the
controller has been initialized (through the `$this->response` getter, for instance), the result
is used as the body of the response; otherwise,  the result is returned as is.





### Before the action is executed

The event `ICanBoogie\Routing\Controller::action:before` of class
[Controller\BeforeActionEvent][] is fired before the `action()` method is invoked. Event hooks may
use this event to provide a response and thus cancelling the action. Event hooks may also use
this event to alter the controller before the action is executed.





### After the action is executed

The event `ICanBoogie\Routing\Controller::action:before` of class [Controller\ActionEvent][]
is fired after the `action()` method was invoked. Event hooks may use this event to alter the
result of the method.





### Basic controllers

Basic controllers extend from [Controller][] and must implement the `action()` method.

> **Note:** The `action()` method is invoked _from within_ the controller, by the `__invoke()` method,
> and should be defined as _protected_. The `__invoke()` method is final, thus cannot be overridden.

```php
<?php

namespace App\Modules\Articles\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;

class DeleteController extends Controller
{
    protected function action(Request $request)
    {
        // Your code goes here, and should return a string or a Response instance
    }
}
```

Although any class implementing `__invoke()` is suitable as a controller, it is recommended to
extend [Controller][] as it makes accessing your application features much easier. Also, you might
benefit from prototype methods and event hooks attached to the [Controller][] class, such as the
`view` property added by the [icanboogie/view][] package.

The following properties are provided by the [Controller][] class:

- `name`: The name of the controller, extracted from its class name e.g. "articles_delete".
- `request`: The request being dispatched.
- `route`: The route being dispatched.





### Action controllers

Here's an example of an action controller, details are available in the [Action controllers
documentation](docs/ActionControllers.md).

```php
<?php

use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Controller\ActionTrait;

final class ArticleController extends ControllerAbstract
{
    use ActionTrait;

    private function list(): string
    {
        // …
    }

    private function show(): string
    {
        // …
    }
}
```










#### Defining resource routes using `RouteMaker`

Given a resource name and a controller, the `RouteMaker::resource()` method makes the various
routes required to handle a resource. Options can be specified to filter the routes to create,
specify the name of the _key_ property and/or it's regex constraint, or name routes.

The following example demonstrates how to create routes for an _article_ resource:

```php
<?php

namespace App;

use ICanBoogie\Routing\RouteMaker as Make;

// create all resource actions definitions
$definitions = Make::resource('articles', ArticlesController::class);

// only create the _list_ definition
$definitions = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ONLY => Make::ACTION_LIST

]);

// only create the _list_ and _show_ definitions
$definitions = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ONLY => [ Make::ACTION_LIST, Make::ACTION_SHOW ]

]);

// create definitions except _destroy_
$definitions = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_EXCEPT => Make::ACTION_DELETE

]);

// create definitions except _updated_ and _destroy_
$definitions = Make::resource('articles', PhotosController::class, [

    Make::OPTION_EXCEPT => [ Make::ACTION_UPDATE, Make::ACTION_DELETE ]

]);

// specify _key_ property name and its regex constraint
$definitions = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_ID_NAME => 'uuid',
    Make::OPTION_ID_REGEX => '{:uuid:}'

]);

// specify the identifier of the _create_ definition
$definitions = Make::resource('articles', ArticlesController::class, [

    Make::OPTION_AS => [

        Make::ACTION_CREATE => 'articles:build'

    ]

]);
```

> **Note:** It is not required to define all the resource actions, only define the one you actually need.





### Closure based routes

A simple closure can be used to handle to a route. A [Controller][] instance is created to wrap and
bound the closure, thus you can write your closure like you would a regular controller action
method.

```php
<?php

/* @var $routes \ICanBoogie\Routing\RouteCollection */

$routes->get('/hello/:name', function ($name) {

    /* @var $this \ICanBoogie\Routing\Controller */

    return "$name === {$this->request['name']}";

});
```





## Exceptions

The exceptions defined by the package implement the `ICanBoogie\Routing\Exception` interface,
so that they are easy to recognize:

```php
<?php

try
{
    // …
}
catch (\ICanBoogie\Routing\Exception $e)
{
    // a routing exception
}
catch (\Exception $e)
{
    // another type of exception
}
```

The following exceptions are defined:

- [ActionNotDefined][]: Thrown when an action is not defined, for instance when a route handled
by a controller using [ActionTrait][] has an empty `action` property.
- [ControllerNotDefined][]: Thrown when trying to define a route without a controller nor location.
- [InvalidPattern][]: Thrown when trying to define a route without pattern.
- [RouteNotDefined][]: Thrown when trying to obtain a route that is not defined in a
[RouteCollection][] instance.





## Helpers

The following helpers are available:

- [contextualize](https://icanboogie.org/api/routing/master/function-ICanBoogie.Routing.contextualize.html): Contextualize a pathname.
- [decontextualize](https://icanboogie.org/api/routing/master/function-ICanBoogie.Routing.decontextualize.html): Decontextualize a pathname.
- [absolutize_url](https://icanboogie.org/api/routing/master/function-ICanBoogie.Routing.absolutize_url.html): Absolutize an URL.





### Patching helpers

Helpers can be patched using the `Helpers::patch()` method.

The following code demonstrates how routes can _start_ with the custom path "/my/application":

```php
<?php

use ICanBoogie\Routing;

$path = "/my/application";

Routing\Helpers::patch('contextualize', function($str) use($path) {

    return $path . $str;

});

Routing\Helpers::patch('decontextualize', function($str) use($path) {

    if (strpos($str, $path . '/') === 0)
    {
        $str = substr($str, strlen($path));
    }

    return $str;

});
```





----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/ICanBoogie/Routing/actions).

[![Tests](https://github.com/ICanBoogie/Routing/workflows/test/badge.svg?branch=master)](https://github.com/ICanBoogie/Routing/actions?query=workflow%3Atest)
[![Static Analysis](https://github.com/ICanBoogie/Routing/workflows/static-analysis/badge.svg?branch=master)](https://github.com/ICanBoogie/Routing/actions?query=workflow%3Astatic-analysis)
[![Code Style](https://github.com/ICanBoogie/Routing/workflows/code-style/badge.svg?branch=master)](https://github.com/ICanBoogie/Routing/actions?query=workflow%3Acode-style)



## Testing

Run `make test-container` to create and log into the test container, then run `make test` to run the
test suite. Alternatively, run `make test-coverage` to run the test suite with test coverage. Open
`build/coverage/index.html` to see the breakdown of the code coverage.





## License

**icanboogie/routing** is released under the [New BSD License](LICENSE).





[ControllerBindings]:                  https://icanboogie.org/api/bind-routing/5.0/class-ICanBoogie.Binding.Routing.ControllerBindings.html
[Response]:                            https://icanboogie.org/api/http/4.0/class-ICanBoogie.HTTP.Response.html
[Request]:                             https://icanboogie.org/api/http/4.0/class-ICanBoogie.HTTP.Request.html
[RequestDispatcher]:                   https://icanboogie.org/api/http/4.0/class-ICanBoogie.HTTP.RequestDispatcher.html
[documentation]:                       https://icanboogie.org/api/routing/5.0/
[ActionNotDefined]:                    https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.ActionNotDefined.html
[ActionTrait]:                         https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Controller.ActionTrait.html
[Controller]:                          https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Controller.html
[Controller\BeforeActionEvent]:        https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Controller.BeforeActionEvent.html
[Controller\ActionEvent]:              https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Controller.ActionEvent.html
[ControllerNotDefined]:                https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.ControllerNotDefined.html
[FormattedRoute]:                      https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.FormattedRoute.html
[Pattern]:                             https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Pattern.html
[InvalidPattern]:                      https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.PatternNotDefined.html
[ResourceTrait]:                       https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Controller.ResourceTrait.html
[Route]:                               https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Route.html
[Route\RescueEvent]:                   https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.Route.RescueEvent.html
[RouteCollection]:                     https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteCollection.html
[RouteDispatcher]:                     https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteDispatcher.html
[RouteDispatcher\BeforeDispatchEvent]: https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteDispatcher.BeforeDispatchEvent.html
[RouteDispatcher\DispatchEvent]:       https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteDispatcher.DispatchEvent.html
[RouteMaker]:                          https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteMaker.html
[RouteNotDefined]:                     https://icanboogie.org/api/routing/5.0/class-ICanBoogie.Routing.RouteNotDefined.html
[ICanBoogie]:                          https://github.com/ICanBoogie/ICanBoogie
[icanboogie/bind-routing]:             https://github.com/ICanBoogie/bind-routing
[icanboogie/service]:                  https://github.com/ICanBoogie/service
[icanboogie/view]:                     https://github.com/ICanBoogie/View
[RESTful]:                             https://en.wikipedia.org/wiki/Representational_state_transfer

[RouteProvider\ByAction]:              lib/RouteProvider/ByAction.php
[RouteProvider\ById]:                  lib/RouteProvider/ById.php
[RouteProvider\ByUri]:                 lib/RouteProvider/ByUri.php
