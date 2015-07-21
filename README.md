# Routing

[![Release](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Build Status](https://img.shields.io/travis/ICanBoogie/Routing/master.svg)](http://travis-ci.org/ICanBoogie/Routing)
[![HHVM](https://img.shields.io/hhvm/icanboogie/routing.svg)](http://hhvm.h4cc.de/package/icanboogie/routing)
[![Code Quality](https://img.shields.io/scrutinizer/g/ICanBoogie/Routing/master.svg)](https://scrutinizer-ci.com/g/ICanBoogie/Routing)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Routing/master.svg)](https://coveralls.io/r/ICanBoogie/Routing)
[![Packagist](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A request is mapped
to a route, which in turn gets dispatched to a controller, and possibly an action. If the
process is successful a response is returned. Many events are fired during the process to allow
event hooks to alter the request, the route, the controller, or the response.





## Dispatching a request

The package provides a request dispatcher that can be used on its own, or as a _domain dispatcher_
of a [RequestDispatcher][] instance.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\RouteCollection;

$routes = new RouteCollection([

	'articles/delete' => [
	
		'controller' => 'ArticlesController#delete',
		'pattern' => '/articles/<id:\d+>',
		'via' => Request::METHOD_DELETE
	
	]

]);

$request = Request::from([

	'url' => "/articles/123",
	'is_delete' => true

]);

$dispatcher = new RouteDispatcher($routes);
$response = $dispatcher($request);
$response();
```





### Before a route is dispatched

Before a route is dispatched the `ICanBoogie\Routing\RouteDispatcher::dispatch:before` event of class
[RouteDispatcher\BeforeDispatchEvent][] is fired. Event hooks may use this event to provide a response
and thus cancel the dispatching.





### A route is dispatched

The `ICanBoogie\Routing\RouteDispatcher::dispatch` event of class [RouteDispatcher\DispatchEvent][]
is fired if the route has been dispatched successfully. Event hooks may use this event to alter the
response.





### Rescuing an exception

If an exception is raised during the dispatch, the `ICanBoogie\Routing\Route::rescue` event
of class [Route\RescueEvent][] is fired. Event hooks may use this event to rescue the route and
provide a response, or replace the exception that will be thrown if the rescue fails.





## Defining routes

Routes are usually defined in `routes` configuration fragments, but can also be defined during
runtime. A pattern is required to define a route, and the controller too if no location is defined.
The following options are available:

- `class`: If the route should be instantiated from a class other than [Route][].
- `location`: To redirect the route to another location.
- `via`: If the route needs to respond to one or more HTTP methods.

The options used to define a route are copied to its instance, even custom ones, which might be
useful to provide additional information to a controller.

The [PatternNotDefined][] exception is thrown if the pattern is not defined, and the
[ControllerNotDefined][] exception is thrown if the controller and the location are not defined.




### Route pattern

A pattern is used to map a route to a controller.

Route pattern RegExs are extended with the following:

- `{:uuid:}`: Matches [Universally unique identifiers](https://en.wikipedia.org/wiki/Universally_unique_identifier) (UUID).

```php
<?php

$routes->any('/articles/<uuid:{:uuid:}>/edit', function() {});
```





### Defining routes during runtime

Routes can be defined during runtime using the [RouteCollection][] instance that is provided to the
dispatcher.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\RouteDispatcher;
use ICanBoogie\Routing\RouteCollection;

$routes = new RouteCollection;

$routes->any('/', function(Request $request) { }, [ 'as' => 'home' ]);
$routes->any('/articles', function(Request $request) { }, [ 'as' => 'articles' ]);
$routes->get('/articles/new', function(Request $request) { }, [ 'as' => 'articles:new' ]);
$routes->post('/articles/new', function(Request $request) { }, [ 'as' => 'articles:create' ]);
$routes->delete('/articles/<nid:\d+>', function(Request $request) { }, [ 'as' => 'articles:delete' ]);

$dispatcher = new RouteDispatcher($routes);

# routes can also be defined afterwards
$routes->any('/read-write', function(Request $request) { }, [ 'via' => [ 'GET', 'POST' ] ]);
```





### Defining routes using configuration fragments

If the package is bound to [ICanBoogie][] using [icanboogie/bind-routing][], routes can be defined
using `routes` configuration fragments. Refer to [icanboogie/bind-routing][] documentation to
learn more about this feature.





## Mapping a path to a route

Routes are mapped using a [RouteCollection][] instance. A HTTP method and a namespace can optionally
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

A route is represented by a [Route][] instance. It is usually created from an array definition,
and contain all the properties of its definition.

```php
<?php

$route = $routes['articles:view'];
echo get_class($route); // ICanBoogie\Routing\Route;
```

A route can be formatted into a relative URL using its `format()` method and appropriate properties.
The method returns a [FormattedRoute][] instance, which can be used as a string. The following
properties are available:

- `url`: The URL contextualized with `contextualize()`.
- `absolute_url`: The contextualized URL _absolutized_ with the `absolute_url()` function.

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





### Assigning a formatting value to a route

The `assign()` method is used to assign a formatting value to a route. It returns an updated
clone of the route which can be formatted without requiring a formatting value. This is very
helpful when you need to pass around an instance of a route that is ready to be formatted.

The following example demonstrates how the `assign()` method can be used to assign a formatting
value to a route, that can later be used like a URL string:

```php
<?php

use ICanBoogie\Routing\RouteCollection;

$routes = new RouteCollection([

	'article:show' => [
	
		'pattern' => '/articles/<year:\d{4}>-<month:\d{2}>.html',
		'controller' => 'ArticlesController#show'
	
	]

]);

$route = $routes['article:show']->assign([ 'year' => 2015, 'month' => '02' ]);
$routes['article:show'] === $routes['article:show'];   // true
$route === $routes['article:show'];                    // false
$route->formatting_value;                              // [ 'year' => 2015, 'month' => 02 ]
$route->has_formatting_value;                          // true

echo $route;
// /articles/2015-02.html
echo $route->absolute_url;
// http://icanboogie.org/articles/2015-02.html
echo $route->format([ 'year' => 2016, 'month' => 10 ]);
// /articles/2016-10.html
```

**Note:** Assigning a formatting value to an _assigned_ route creates another instance of the
route. Also, the formatting value is reset when an _assigned_ route is cloned.

Whether a route has an assigned formatting value or not, the `format()` method still requires
a formatting value, it does *not* use the assign formatting value. Thus, if you want to format
a route with its assigned formatting value use the `formatting_value` property:

```php
<?php

echo $route->format($route->formatting_value);
```





## Controllers

Previous examples demonstrated how closures could be used to handle routes. Closures are
perfectly fine when you start building your application, but as soon as it grows you might want
to use controller classes instead to better organize your application. You can map each route to
its [Controller][] class, or use the [ActionTrait][] or [ResourceTrait][] to group related HTTP
request handling logic into a  controller.





### Basic controllers

Basic controllers extend from [Controller][] and must implement the `action()` method.

**Note:** The `action()` method is invoked _from within_ the controller, by the `__invoke()` method,
and should be defined as _protected_.

```php
<?php

namespace App\Modules\Articles;

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

The following properties are provided by the class:

- `name`: The name of the controller, extracted from its class name e.g. "my_articles".
- `request`: The request being dispatched.
- `route`: The route matching the request.

Also, undefined properties are forwarded to the application, thus you can use
`$this->modules` instead of `$this->app->modules`.





#### Controller response

When invoked, the controller should return a result, or `null` if it can't handle the request.
The result of the `action()` method is handled by the `__invoke()` method: if the result is a
[Response][] instance it is returned as is; if the [Response][] instance attached to the
controller has been initialized (through the `$this->response` getter, for instance), the result
is used as the body of the response; otherwise,  the result is returned as is.





#### Before the action is executed

The event `ICanBoogie\Routing\Controller::action:before` of class
[Controller\BeforeActionEvent][] is fired before the `action()` method is invoked. Event hooks may
use this event to provide a response and thus cancelling the action. Event hooks may also use
this event to alter the controller before the action is executed.





#### After the action is executed

The event `ICanBoogie\Routing\Controller::action:before` of class [Controller\ActionEvent][]
is fired after the `action()` method was invoked. Event hooks may use this event to alter the
result of the method.





### Action controllers

Action controllers are used to group related HTTP request handling logic into a class and use
HTTP methods to separate concerns. An action controller is created by extending the
[Controller][] class and using [ActionTrait][].

The following example demonstrates how an action controller can be used to display a contact
form, handle its submission, and redirect the user to a _success_ page. The action invoked
inside the controller is defined after the '#' character.

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
for methods that handle any kind of HTTP method, they are a fallback when more accurate methods
are not available. If you don't care about that, you can omit the HTTP method.

```php
<?php

use ICanBoogie\Routing\Controller;

class AppController extends Controller
{
	use Controller\ActionTrait;
	
	protected function action_any_contact()
	{
		return new ContactForm;
	}

	protected function action_post_contact()
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

	protected function action_contact_success()
	{
		return "Your message has been sent.";
	}
}
```





### Resource controllers

Resource controllers are used to group the actions required to handle a resource in a
[RESTful][] fashion. A resource controller is created by extending the [Controller][] class and
using [ResourceTrait][].

**Note:** Because [ResourceTrait][] uses [ActionTrait][], _regular_ actions can be mixed with
_resource_ actions, although _resource methods_ win over _action methods_.

The following table list the verbs/routes and their corresponding action.

| HTTP verb | Path                  | Action  | Used for                                 |
| --------- | --------------------- | ------- | ---------------------------------------- |
| GET       | /{resource}           | index   | A list of {resource}                     |
| GET       | /{resource}/new       | create  | A form for creating a new {resource}     |
| POST      | /{resource}           | store   | Create a new {resource}                  |
| GET       | /{resource}/{id}      | show    | A specific {resource}                    |
| GET       | /{resource}/{id}/edit | edit    | A form for editing a specific {resource} |
| PATCH/PUT | /{resource}/{id}      | update  | Update a specific {resource}             |
| DELETE    | /{resource}/{id}      | destroy | Deletes a specific {resource}            |

The routes listed are more of a guideline than a requirement, still the actions are important.
Indeed, contrary to _regular_ actions, the corresponding method have the exact same name.

The following example demonstrates how the resource controller or _photos_ resources could be
implemented. The example implements all actions, but you are free to implement only some of them.

```php
<?php

use ICanBoogie\Routing\Controller;
use ICanBoogie\Routing\Controller\ResourceTrait;

class PhotosController extends Controller
{
	use ResourceTrait;

	protected function index()
	{
		// …
	}
	
	protected function create()
	{
		// …
	}
	
	protected function store()
	{
		// …
	}
	
	protected function show($id)
	{
		// …
	}
	
	protected function edit($id)
	{
		// …
	}
	
	protected function update($id)
	{
		// …
	}

	protected function destroy($id)
	{
		// …
	}
}
```




#### Defining resource routes

Given a resource name and a controller, the `RoutesMaker::resource()` method makes the various
routes required to handle a resource. Options can be specified to filter the routes to create,
specify the name of the _key_ property and/or it's regex constraint, or name routes.

The following code demonstrates how to create routes for a _photo_ resource:

```php
<?php

namespace App;

use ICanBoogie\Routing\RoutesMaker as Make;

$definitions = Make::resource('photos', PhotosController::class);

// only create the _index_ route
$definitions = Make::resource('photos', PhotosController::class, [

	'only' => 'index'

]);

// only create the _index_ and _show_ routes
$definitions = Make::resource('photos', PhotosController::class, [

	'only' => [ 'index', 'show' ]

]);

// create routes except _destroy_ route
$definitions = Make::resource('photos', PhotosController::class, [

	'except' => 'destroy'

]);

// create routes except _updated_ and _destroy_ routes
$definitions = Make::resource('photos', PhotosController::class, [

	'except' => [ 'update', 'destroy' ]

]);

// specify _key_ property name and its regex constraint
$definitions = Make::resource('photos', PhotosController::class, [

	'id_name' => 'uuid',
	'id_regex' => '[[:uuid:]]{36}'

]);

// specify the identifier of the _create_ route
$definitions = Make::resource('photos', PhotosController::class, [

	'as' => [ 'create' => 'photos:build' ]

]);
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
- [PatternNotDefined][]: Thrown when trying to define a route without pattern.
- [RouteNotDefined][]: Thrown when trying to obtain a route that is not defined in a
[RouteCollection][] instance.





## Helpers

The following helpers are available:

- [contextualize](http://api.icanboogie.org/routing/function-ICanBoogie.Routing.contextualize.html): Contextualize a pathname.
- [decontextualize](http://api.icanboogie.org/routing/function-ICanBoogie.Routing.decontextualize.html): Decontextualize a pathname.
- [absolutize_url](http://api.icanboogie.org/routing/function-ICanBoogie.Routing.absolutize_url.html): Absolutize an URL.





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

The package requires PHP 5.5 or later.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/):

```
$ composer require icanboogie/routing
```

The following package is required, you might want to check it out:

* [icanboogie/http](https://packagist.org/packages/icanboogie/http)





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/Routing), its repository can be cloned with the following command line:

	$ git clone https://github.com/ICanBoogie/Routing.git





## Documentation

The package is documented as part of the [ICanBoogie][] framework
[documentation](http://api.icanboogie.org/routing/). You can generate the documentation for the package and its dependencies with the `make doc` command. The documentation is generated in the `build/docs` directory. [ApiGen](http://apigen.org/) is required. The directory can later be cleaned with the `make clean` command.





## Testing

The test suite is ran with the `make test` command. [PHPUnit](https://phpunit.de/) and [Composer](http://getcomposer.org/) need to be globally available to run the suite. The command installs dependencies as required. The `make test-coverage` command runs test suite and also creates an HTML coverage report in `build/coverage`. The directory can later be cleaned with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/ICanBoogie/Routing/master.svg)](http://travis-ci.org/ICanBoogie/Routing)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Routing/master.svg)](https://coveralls.io/r/ICanBoogie/Routing)





## License

**icanboogie/routing** is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[icanboogie/bind-routing]: https://github.com/ICanBoogie/bind-routing
[icanboogie/view]: https://github.com/ICanBoogie/View
[ActionNotDefined]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.ActionNotDefined.html
[ActionTrait]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Controller.ActionTrait.html
[Controller]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Controller.html
[Controller\BeforeActionEvent]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Controller.BeforeActionEvent.html
[Controller\ActionEvent]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Controller.ActionEvent.html
[ControllerNotDefined]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.ControllerNotDefined.html
[FormattedRoute]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.FormattedRoute.html
[ICanBoogie]: https://github.com/ICanBoogie/ICanBoogie
[ICanBoogie\HTTP\RouteDispatcher]: http://api.icanboogie.org/http/class-ICanBoogie.HTTP.RouteDispatcher.html
[Pattern]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Pattern.html
[PatternNotDefined]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.PatternNotDefined.html
[Request]: http://api.icanboogie.org/http/class-ICanBoogie.HTTP.Request.html
[RequestDispatcher]: http://api.icanboogie.org/http/class-ICanBoogie.HTTP.RequestDispatcher.html
[ResourceTrait]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Controller.ResourceTrait.html
[Response]: http://api.icanboogie.org/http/class-ICanBoogie.HTTP.Response.html
[RESTful]: https://en.wikipedia.org/wiki/Representational_state_transfer
[Route]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Route.html
[Route\RescueEvent]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.Route.RescueEvent.html
[RouteDispatcher\BeforeDispatchEvent]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.RouteDispatcher.BeforeDispatchEvent.html
[RouteDispatcher\DispatchEvent]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.RouteDispatcher.DispatchEvent.html
[RouteCollection]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.RouteCollection.html
[RouteNotDefined]: http://api.icanboogie.org/routing/class-ICanBoogie.Routing.RouteNotDefined.html
