# Routing

[![Packagist](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/routing.svg)](https://scrutinizer-ci.com/g/ICanBoogie/routing)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/routing.svg)](https://coveralls.io/r/ICanBoogie/routing)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A Request is mapped to a
Route, which in turn is mapped to a Responder. If the process is successful a response is returned.
Events are emitted along the way to allow listeners to alter the request or the response, or recover
from failure.

The following example is an overview of a request processing. The routing components are part of the
stack of responder providers.

```php
<?php

namespace ICanBoogie\HTTP;

/* @var ResponderProvider $responder_provider */

// The request is usually created from the $_SERVER super global.
$request = Request::from($_SERVER);

// The Responder Provider matches a request with a Responder
$responder = $responder_provider->responder_for_request($request);

// The Responder responds to the request with a Response, it might also throw an exception.
$response = $responder->respond($request);

// The response is sent to the client.
$response();
```



#### Installation

```bash
composer require icanboogie/routing
```


## A route

A route is represented with a [Route][] instance. Two parameters are required to create an instance:
`pattern` and `action`. `pattern` is the pattern to match or generate a URL. `action` is an
identifier for an action, which can be used to match with a Responder.



### The route pattern

A pattern is used to match a URL with a route. Placeholders may be used to match multiple URL to a
single route and extract its parameters. Three types of placeholder are available:

- Relaxed placeholder: Only the name of the parameter is specified, it matches anything until
  the following part. e.g. `/articles/:id/edit`.

- Constrained placeholder: A regular expression is used to match the parameter value.
  e.g. `/articles/<id:\d+>/edit` where `<id:\d+>` is the placeholder for the `id` parameter which
  value must matches `/^\d+$/`.

- Anonymous constrained placeholder: Same as the constrained placeholder, except the parameter has
  no name but an index e.g. `/articles/<\d+>/edit` where `<\d+>` in a placeholder which index is 0.

Additionally, the joker character `*`—which can only be used at the end of a pattern—matches
anything. e.g. `/articles/123*` matches `/articles/123` and `/articles/123456` as well.

Finally, constraints RegExp are extended with the following:

- `{:sha1:}`: Matches [SHA-1](https://en.wikipedia.org/wiki/SHA-1) hashes. e.g. `/files/<hash:{:sha1:}>`.
- `{:uuid:}`: Matches [Universally unique identifiers](https://en.wikipedia.org/wiki/Universally_unique_identifier)
  (UUID). e.g. `/articles/<uuid:{:uuid:}>/edit`.

You can use them in any combination:

- `/blog/:year-:month-:slug`
- `/blog/<year:\d{4}>-<month:\d{2}>-:slug`
- `/images/<uuid:{:uuid:}>/<size:\d+x|x\d+|\d+x\d+>*`



## Route providers

Route providers are used to find the route that matches a predicate. Simple route providers are
often decorated with more sophisticated ones that can improve performance.

Here's an overview of a route provider usage, details are available in the [Route Providers
documentation](docs/RouteProviders.md).

```php
<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\RouteProvider\ByAction;
use ICanBoogie\Routing\RouteProvider\ByUri;

/* @var RouteProvider $routes */

$routes->route_for_predicate(new ByAction('articles:show'));
$routes->route_for_predicate(new ByUri('/articles/123', RequestMethod::METHOD_GET));
$routes->route_for_predicate(fn(Route $route) => $route->action === 'articles:show');
```



## Responding to a request

A request can be dispatched to a matching Responder provided a route matches the request URI and
method.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\RouteProvider;

$routes = new RouteProvider\Immutable([

    new Route('/articles/<id:\d+>', 'articles:delete', RequestMethod::METHOD_DELETE)

]);

$request = Request::from([

    Request::OPTION_URI => "/articles/123",
    Request::OPTION_METHOD => RequestMethod::METHOD_DELETE,

]);

/* @var Responder $responder */

$response = $responder->respond($request);
```





### Rescuing a route

If an exception is raised during dispatching, the `ICanBoogie\Routing\Route::rescue` event
of class [Route\RescueEvent][] is fired. Event hooks may use this event to rescue the route and
provide a response, or replace the exception that will be thrown if the rescue fails.





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
- [InvalidPattern][]: Thrown when trying to define a route without pattern.



----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/ICanBoogie/Routing/actions).

[![Tests](https://github.com/ICanBoogie/routing/workflows/test/badge.svg?branch=master)](https://github.com/ICanBoogie/routing/actions?query=workflow%3Atest)
[![Static Analysis](https://github.com/ICanBoogie/routing/workflows/static-analysis/badge.svg?branch=master)](https://github.com/ICanBoogie/routing/actions?query=workflow%3Astatic-analysis)
[![Code Style](https://github.com/ICanBoogie/routing/workflows/code-style/badge.svg?branch=master)](https://github.com/ICanBoogie/routing/actions?query=workflow%3Acode-style)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you are expected to uphold this code.



## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.



## License

**icanboogie/routing** is released under the [BSD-3-Clause](LICENSE).




[ICanBoogie]:                          https://icanboogie/org
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
[icanboogie/bind-routing]:             https://github.com/ICanBoogie/bind-routing
[icanboogie/service]:                  https://github.com/ICanBoogie/service
[icanboogie/view]:                     https://github.com/ICanBoogie/View
[RESTful]:                             https://en.wikipedia.org/wiki/Representational_state_transfer

[RouteProvider\ByAction]:              lib/RouteProvider/ByAction.php
[RouteProvider\ById]:                  lib/RouteProvider/ById.php
[RouteProvider\ByUri]:                 lib/RouteProvider/ByUri.php
