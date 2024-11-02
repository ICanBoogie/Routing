# Routing

[![Packagist](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Code Coverage](https://coveralls.io/repos/github/ICanBoogie/Routing/badge.svg?branch=6.0)](https://coveralls.io/r/ICanBoogie/Routing?branch=6.0)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A Request is mapped to a
Route, which in turn is mapped to a Responder. If the process is successful a response is returned.
Events are emitted along the way to allow listeners to alter the request or the response, or recover
from failure.

The following example is an overview of request processing. The routing components are part of the
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

```shell
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

Here is an overview of a route provider usage, details are available in the [Route Providers
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





## Controllers

Previous examples demonstrated how closures could be used to handle routes. Closures are
perfectly fine when you start building your application, but as soon as it grows, you might want
to use controller classes instead to better organize your application. You can map each route to
its [ControllerAbstract][] class, or use the [ActionTrait][] to group related HTTP
request handling logic into a single controller.





### Controller response

When invoked, the controller should return a result, or `null` if it can't handle the request.
The result of the `action()` method is handled by the `__invoke()` method: if the result is a
[Response][] instance, it is returned as is; if the [Response][] instance attached to the
controller has been initialized (through the `$this->response` getter, for instance), the result
is used as the body of the response; otherwise, the result is returned as is.





### Before the action is executed

[Controller\BeforeActionEvent][] is emitted before the `action()` method is invoked. Listeners may
provide a response and thus cancel the action. Event hooks may also use this event to alter the
controller before the action is executed.





### After the action is executed

[Controller\ActionEvent][] is emitted after the `action()` method was invoked. Listeners may alter
the result of the method.





### Basic controllers

Basic controllers extend from [ControllerAbstract][] and must implement the `action()` method.

> [!NOTE]
> The `action()` method is invoked _from within_ the controller, by the `__invoke()` method,
> and is better defined as _protected_. The `__invoke()` method is final, thus can't be overridden.

```php
<?php

namespace App\Modules\Articles\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;

class DeleteController extends Controller
{
    protected function action(Request $request)
    {
        // Your code goes here and should return a string or a Response instance
    }
}
```

Although any class implementing `__invoke()` is suitable as a controller, it is recommended to
extend [ControllerAbstract][] as it makes accessing your application features much easier. Also, you
might benefit from prototype methods and event hooks attached to the [ControllerAbstract][] class,
such as the `view` property added by the [icanboogie/view][] package.

The following properties are provided by the [ControllerAbstract][] class:

- `name`: The name of the controller, extracted from its class name e.g. `articles_delete`.
- `request`: The request being dispatched.
- `route`: The route being dispatched.





### Action controllers

Here is an example of an action controller, details are available in the [Action controllers
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
so that they're easy to recognize:

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
- [InvalidPattern][]: Thrown when trying to define a route without a pattern.



----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/ICanBoogie/Routing/actions).

[![Tests](https://github.com/ICanBoogie/Routing/actions/workflows/test.yml/badge.svg?branch=6.0)](https://github.com/ICanBoogie/Routing/actions/workflows/test.yml)
[![Static Analysis](https://github.com/ICanBoogie/Routing/actions/workflows/static-analysis.yml/badge.svg?branch=6.0)](https://github.com/ICanBoogie/Routing/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/ICanBoogie/Routing/actions/workflows/code-style.yml/badge.svg?branch=6.0)](https://github.com/ICanBoogie/Routing/actions/workflows/code-style.yml)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you're expected to uphold this code.



## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.



[ICanBoogie]:                          https://icanboogie/org
[icanboogie/view]:                     https://github.com/ICanBoogie/View
[RESTful]:                             https://en.wikipedia.org/wiki/Representational_state_transfer
[Response]:                            https://github.com/ICanBoogie/HTTP/blob/6.0/lib/Response.php
[Request]:                             https://github.com/ICanBoogie/HTTP/blob/6.0/lib/Request.php
[ActionNotDefined]:                    lib/Exception/ActionNotDefined.php
[ActionTrait]:                         lib/Controller/ActionTrait.php
[ControllerAbstract]:                  lib/ControllerAbstract.php
[Controller\BeforeActionEvent]:        lib/Controller/BeforeActionEvent.php
[Controller\ActionEvent]:              lib/Controller/ActionEvent.php
[InvalidPattern]:                      lib/Exception/InvalidPattern.php
[Route]:                               lib/Route.php
[RouteProvider\ByAction]:              lib/RouteProvider/ByAction.php
[RouteProvider\ById]:                  lib/RouteProvider/ById.php
[RouteProvider\ByUri]:                 lib/RouteProvider/ByUri.php
