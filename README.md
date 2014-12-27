# Routing [![Build Status](https://secure.travis-ci.org/ICanBoogie/Routing.svg?branch=2.0)](http://travis-ci.org/ICanBoogie/Routing)

The Routing package provides classes and helpers to handle URL rewriting in native PHP. It
provides an API to redirect incoming requests to controllers. A request is redirected, or
_mapped_, using a dispatcher and routes, which are usually defined in `routes` configuration
fragments but can also be defined during runtime. Controllers usually return a [Response][],
but can also return a string (or a stringifyable object) to produce a simple `text/html` response.





## Dispatching a request

The Routing package provides a [Request][] dispatcher that can be used as a sub-dispatcher by a
[ICanBoogie\HTTP\Dispatcher][] instance, or as a stand-alone dispatcher.

```php
<?php

use ICanBoogie\Routing\Dispatcher;

$request = Request::from([

	'url' => "/articles/123",
	'is_delete' => true

]);

$dispatcher = new Dispatcher($routes);
$response = $dispatcher($request);
```

Before the route is dispatched the `ICanBoogie\Routing\Dispatcher::dispatch:before` event of class
[BeforeDispatchEvent][] is fired. Event hooks may use this event to provide a response and cancel
the dispatching.

If an exception is raised during the dispatching, the `ICanBoogie\Routing\Route::rescue` event
of class [RescueEvent][] is fired. Event hooks may use this event to rescue the route and
provide a response, or replace the exception that will be thrown if the rescue fails.

The `ICanBoogie\Routing\Dispatcher::dispatch` event of class [DispatchEvent][] is fired if 
the route has been dispatched successfully. Event hooks may use this event to alter the response.





## Defining routes

Routes are usually defined in `routes` configuration fragments, but can also be defined during
runtime. The pattern is required to define a route, and the controller too if no location
is defined. The following options are available:

- `class`: If you want or use another class than [Route][].
- `location`: To redirect the route to another location.
- `via`: If the route needs to respond to one or more HTTP methods.

Defined options are copied into the [Route][] instance, even customized ones. Use this
feature if your controller requires additional information about a route.

The [PatternNotDefined][] exception is thrown if the pattern is not defined, and the
[ControllerNotDefined][] exception is thrown if the controller and the location are not defined.





### Defining routes using `routes` configuration fragments

The most efficient way to define routes is through the `routes` configuration fragments because
it doesn't require application logic (additional code) and the synthesized configuration can be
cached.

```php
<?php

// config/routes.php

use ICanBoogie\HTTP\Request;

return [

	'home' => [

		'pattern' => '/',
		'controller' => 'Website\Routing\Controller'

	],

	'articles' => [

		'pattern' => '/articles',
		'controller' => 'Website\Modules\Blog\Controller'

	],

	'articles:view' => [

		'pattern' => '/articles/:year-:month-:slug.html',
		'controller' => 'Website\Modules\Blog\Controller'

	],

	'articles:new' => [

		'pattern' => '/articles/new',
		'controller' => 'Website\Modules\Blog\Controller',
		'via' => Request::METHOD_GET

	],

	'articles:save' => [

		'pattern' => '/articles',
		'controller' => 'Website\Modules\Blog\Controller',
		'via' => [ Request::METHOD_POST, Request::METHOD_PATCH ]

	],

	'articles:delete' => [

		'pattern' => '/articles/<nid:\d+>',
		'controller' => 'Website\Modules\Blog\Controller',
		'via' => Request::METHOD_DELETE

	]

];
```

Note that using configuration fragments requires [ICanBoogie][].





### Defining routes during runtime

Routes can also be defined during runtime through a [Routes][] instance.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Routes;

$routes = new Routes;

$routes->any('/', function(Request $request) { }, [ 'as' => 'home' ]);
$routes->any('/articles', function(Request $request) { }, [ 'as' => 'articles' ]);
$routes->get('/articles/new', function(Request $request) { }, [ 'as' => 'articles:new' ]);
$routes->post('/articles/new', function(Request $request) { }, [ 'as' => 'articles:create' ]);
$routes->delete('/articles/<nid:\d+>', function(Request $request) { }, [ 'as' => 'articles:delete' ]);
$routes->any('/read-write', function(Request $request) { }, [ 'via' => [ 'GET', 'POST' ] ]);
```





## Mapping a path to a route

A [Routes][] instance is used to map paths to routes. A HTTP method and a namespace can optionally
be specified to determine the route more accurately. The parameters captured from the routes are
stored in the `$captured` variable, passed by reference. If the path contains a query string,
it is parsed and stored under `__query__` in `$captured`.

```php
<?php

use ICanBoogie\HTTP\Request;

$home_route = $routes->find('/?singer=madonna', $captured);
var_dump($captured);   // [ '__query__' => [ 'singer' => 'madonna' ] ]

$articles_delete_route = $routes->find('/articles/123', $captured, Request::METHOD_DELETE);
var_dump($captured);   // [ 'nid' => 123 ]
```





## Route

Routes are represented by [Route][] instances. They are usually created from route definitions, and
contains all the properties of their definition.

```php
<?php

$route = $routes['articles:view'];
echo get_class($route); // ICanBoogie\Routing\Route;
```

A route can be formatted into a relative URL with its `format()` methods and appropriate properties.
The method returns a [FormattedRoute][] instance which can be used as a string. Its `url` property
holds the URL contextualized with `contextualize()` and its `absolute_url` property holds the
contextualized URL absolutized with the `absolute_url()` function.

```php
<?php

$route = $routes['articles:view'];
echo $route->pattern;      // /articles/:year-:month-:slug.html

$url = $route->format([ 'year' => '2014', 'month' => '06', 'slug' => 'madonna-queen-of-pop' ]);
echo $url;                 // /articles/2014-06-madonna-queen-of-pop.html
echo get_class($url);      // ICanBoogie\Routing\FormattedRoute
echo $url->absolute_url;   // http://icanboogie.org/articles/2014-06-madonna-queen-of-pop.html

$url->route === $route;    // true
```





## Controllers

Previous examples demonstrated how closures could be used to handle routes. Closures are
perfectly fine when you start building your application, but as soon as it grows you might want to
use controller classes instead to better organize your application. For instance, the
[ActionController][] can group related HTTP request handling logic into a class.





### Basic controllers

Basic controllers extend from [Controller][] and implement the `respond` method.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Controller;

class MyArticlesController extends Controller
{
	protected function respond(Request $request)
	{
		// Your code goes here, and should return a string or a Response instance
	}
}
```

Although any class implementing `__invoke` is suitable as a controller, it is recommended to extend
[Controller][] because it makes accessing your application features much easier. Also, you might
benefit from prototype methods and event hooks attached to the [Controller][] class. The
following properties are available as well:

- `name`: The name of the controller, extracted from its class name e.g. "my_articles".
- `request`: The request being dispatched.
- `route`: The route matching the request.

Also, undefined properties are forwarded to the application, thus you can use
`$this->app->modules` or simply `$this->modules`.





#### Controller response

The response to the request is obtained by invoking `respond()`, when the result is a [Response][]
instance it is returned as is, when the `$response` property has been initialized the result
is used as its body and the response is returned, otherwise the result is returned as is.

The `ICanBoogie\Routing\Controller::respond:before` event of class
[Controller\BeforeRespondEvent][] is fired before invoking `respond()`, the
`ICanBoogie\Routing\Controller::respond:before` event of class [Controller\RespondEvent][] is
fired after.





### Action controllers

Action controllers can group related HTTP request handling logic into a class and use HTTP methods
to separate concerns.

The following example demonstrate how an action controller can be used to display a contact form,
handle its submission, and redirect the user to a _success_ page. The action invoked inside the
controller is defined after the '#' character.

```php
<?php

// routes.php

return [

	'contact' => [

		'pattern' => '/contact',
		'controller' => 'AppController#contact'

	],

	'contact:ok' => [

		'pattern' => '/contact/success.html'
		'controller' => 'AppController#contact_ok'

	]

];
```

The HTTP method is used as a prefix for the method handling the action. The prefix "any" is used
for method that handle any kind of HTTP method, they are a fallback when more accurate methods are
not available.

```php
<?php

use ICanBoogie\Routing\ActionController

class AppController extends ActionController
{
	protected function any_contact()
	{
		return new ContactForm;
	}

	protected function post_contact()
	{
		$form = new ContactForm;
		$request = $this->request;

		if (!$form->validate($request->params, $errors))
		{
			return $this->redirect($this->routes['contact']);
		}

		// …

		$email = $request['email'];
		$message = $request['message'];

		// …

		return $this->redirect($this->routes['contact:ok']);
	}

	protected function any_contact_success()
	{
		return "Your message has been sent.";
	}
}
```

The `ICanBoogie\Routing\ActionController::action:before` event of class
[ActionController\BeforeActionEvent] if fired before the action method is invoked, the
`ICanBoogie\Routing\ActionController::action` event of class
[ActionController\ActionEvent] is fired after.





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

- [ActionNotDefined][]: Thrown when an action is not defined, for instance when a route using
an [ActionController][] has an empty `action` property'.
- [ControllerNotDefined][]: Thrown when trying to define a route without a controller nor location.
- [PatternNotDefined][]: Thrown when trying to define a route without pattern.
- [RouteNotDefined][]: Thrown when trying to obtain a route that is not defined in a [Routes][] instance.





## Helpers

The following helpers are available:

- [contextualize](http://icanboogie.org/docs/function-ICanBoogie.Routing.contextualize.html): Contextualize a pathname.
- [decontextualize](http://icanboogie.org/docs/function-ICanBoogie.Routing.decontextualize.html): Decontextualize a pathname.
- [absolutize_url](http://icanboogie.org/docs/function-ICanBoogie.Routing.absolutize_url.html): Absolutize an URL.





### Patching helpers

Helpers can be patched using the `Helpers::patch()` method.

The following code demonstrates how routes can _start_ with the custom path "my/application":

```php
<?php

use ICanBoogie\Routing;

$path = "my/application";

Routing\Helpers::patch('contextualize', function ($str) use($path) {

	return $path . $str;

});

Routing\Helpers::patch('decontextualize', function ($str) use($path) {

	if (strpos($str, $path . '/') === 0)
	{
		$str = substr($str, strlen($path));
	}

	return $str;

});
```





----------





## Requirements

The package requires PHP 5.4 or later.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/):

```
composer require icanboogie/routing
```

The following package is required, you might want to check it out:

* [icanboogie/http](https://packagist.org/packages/icanboogie/http)





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/Routing), its repository can be
cloned with the following command line:

	$ git clone https://github.com/ICanBoogie/Routing.git





## Documentation

The package is documented as part of the [ICanBoogie](http://icanboogie.org/) framework
[documentation](http://icanboogie.org/docs/). You can generate the documentation for the package
and its dependencies with the `make doc` command. The documentation is generated in the `docs`
directory. [ApiGen](http://apigen.org/) is required. You can later clean the directory with
the `make clean` command.





## Testing

The test suite is ran with the `make test` command. [Composer](http://getcomposer.org/) is
automatically installed as well as all dependencies required to run the suite. You can later
clean the directory with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://secure.travis-ci.org/ICanBoogie/Routing.svg?branch=2.0)](http://travis-ci.org/ICanBoogie/Routing)





## License

ICanBoogie/Routing is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[ActionController]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.ActionController.html
[ActionController\BeforeActionEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.ActionController.BeforeActionEvent.html
[ActionController\ActionEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.ActionController.ActionEvent.html
[ActionNotDefined]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.ActionNotDefined.html
[BeforeDispatchEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Dispatcher.BeforeDispatchEvent.html
[ICanBoogie]: http://icanboogie.org/
[ICanBoogie\HTTP\Dispatcher]: http://icanboogie.org/docs/namespace-ICanBoogie.HTTP.Dispatcher.html
[Controller]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Controller.html
[Controller\BeforeRespondEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Controller.BeforeRespondEvent.html
[Controller\RespondEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Controller.RespondEvent.html
[ControllerNotDefined]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.ControllerNotDefined.html
[DispatchEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Dispatcher.DispatchEvent.html
[FormattedRoute]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.FormattedRoute.html
[Pattern]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Pattern.html
[PatternNotDefined]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.PatternNotDefined.html
[Request]: http://icanboogie.org/docs/namespace-ICanBoogie.HTTP.Request.html
[RescueEvent]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Route.RescueEvent.html
[response]: http://icanboogie.org/docs/namespace-ICanBoogie.HTTP.Response.html
[Route]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Route.html
[RouteNotDefined]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.RouteNotDefined.html
[Routes]: http://icanboogie.org/docs/namespace-ICanBoogie.Routing.Routes.html
