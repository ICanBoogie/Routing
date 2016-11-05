# Routing

[![Release](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Build Status](https://img.shields.io/travis/ICanBoogie/Routing.svg)](http://travis-ci.org/ICanBoogie/Routing)
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
- `RouteDefinition::CONTROLLER`: The controller class and optional action, or a callable.
- `RouteDefinition::ID`: The identifier of the route.
- `RouteDefinition::VIA`: If the route needs to respond to one or more HTTP methods, e.g.
`Request::METHOD_GET` or `[ Request::METHOD_PUT, Request::METHOD_PATCH ]`.
Defaults: `Request::METHOD_GET`.
- `RouteDefinition::LOCATION`: To redirect the route to another location.
- `RouteDefinition::CONSTRUCTOR`: If the route should be instantiated from a class other than
[Route][].

A route definition is considered valid when the `RouteDefinition::PATTERN` parameter is defined
along one of `RouteDefinition::CONTROLLER` or `RouteDefinition::LOCATION`. [PatternNotDefined][] is
thrown if `RouteDefinition::PATTERN` is missing, and [ControllerNotDefined][] is thrown if both
`RouteDefinition::CONTROLLER` and `RouteDefinition::LOCATION` are missing.

> **Note:** You can add any parameter you want to the route definition, they are used to create
the route instance, which might be useful to provide additional information to a controller.
Better use a custom route class though.





### Route patterns

A pattern is used to match a URL with a route. Placeholders may be used to match multiple URL to a
single route and extract its parameters. Three types of placeholder are available:

- Relaxed placeholder: Only the name of the parameter is specified, it matches anything until
the following part. e.g. `/articles/:id/edit` where `:id` is the placeholder for
the `RouteDefinition::ID` parameter.
 
- Constrained placeholder: A regular expression is used to match the parameter value.
e.g. `/articles/<id:\d+>/edit` where `<id:\d+>` is the placeholder for the `id` parameter
which value must match `/^\d+$/`.

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

The `controller` key specifies the callable to invoke, or the class name of a callable.
The following value types are accepted:

- A controller class: `ArticlesShowController`
- A controller action: `ArticlesController#show`, where `ArticlesController` is
the controller class, and `show` is the action.
- A callable: `function() {}`, `new ArticlesShowController`, `ArticlesController::show`,
`articles_controller_show`, …





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
use ICanBoogie\Routing\RouteDefinition;

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
use ICanBoogie\Routing\RouteCollection;
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

A route is represented by a [Route][] instance. It is usually created from a definition array
and contains all the properties of its definition.

```php
<?php

$route = $routes['articles:show'];
echo get_class($route); // ICanBoogie\Routing\Route;
```

A route can be formatted into a relative URL using its `format()` method and appropriate
formatting parameters. The method returns a [FormattedRoute][] instance, which can be used as
a string. The following properties are available:

- `url`: The URL contextualized with `contextualize()`.
- `absolute_url`: The contextualized URL _absolutized_ with the `absolute_url()` function.

```php
<?php

$route = $routes['articles:show'];
echo $route->pattern;      // /articles/:year-:month-:slug.html

$url = $route->format([ 'year' => '2014', 'month' => '06', 'slug' => 'madonna-queen-of-pop' ]);
echo $url;                 // /articles/2014-06-madonna-queen-of-pop.html
echo get_class($url);      // ICanBoogie\Routing\FormattedRoute
echo $url->absolute_url;   // https://icanboogie.org/articles/2014-06-madonna-queen-of-pop.html

$url->route === $route;    // true
```

You can format a route using a record, or any other object, as well:

```php
<?php

$record = $app->models['articles']->one;
$url = $routes['articles:show']->format($record);
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
use ICanBoogie\Routing\RouteDefinition;

$routes = new RouteCollection([

	'article:show' => [

		RouteDefinition::PATTERN => '/articles/<year:\d{4}>-<month:\d{2}>.html',
		RouteDefinition::CONTROLLER => ArticlesController::class,
		RouteDefinition::ACTION => 'show'

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
// https://icanboogie.org/articles/2015-02.html
echo $route->format([ 'year' => 2016, 'month' => 10 ]);
// /articles/2016-10.html
```

> **Note:** Assigning a formatting value to an _assigned_ route creates another instance of the
route. Also, the formatting value is reset when an _assigned_ route is cloned.

Whether a route has an assigned formatting value or not, the `format()` method still requires
a formatting value, it does *not* use the assign formatting value. Thus, if you want to format
a route with its assigned formatting value, use the `formatting_value` property:

```php
<?php

echo $route->format($route->formatting_value);
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
and should be defined as _protected_. The `__invoke()` method is final, thus cannot be overridden.

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

Action controllers are used to group related HTTP request handling logic into a class and use
HTTP methods to separate concerns. An action controller is created by extending the
[Controller][] class and using [ActionTrait][].

The following example demonstrates how an action controller can be used to display a contact
form, handle its submission, and redirect the user to a _success_ page. The action invoked
inside the controller is defined after the "#" character. The action may as well be defined
using the `action` key.

```php
<?php

// routes.php

use ICanBoogie\Routing\RouteDefinition;

return [

	'contact' => [

		RouteDefinition::PATTERN => '/contact',
		RouteDefinition::CONTROLLER => AppController::class . '#contact'

	],
	
	# or
	
	'contact' => [

		RouteDefinition::PATTERN => '/contact',
		RouteDefinition::CONTROLLER => AppController::class,
		RouteDefinition::ACTION => 'contact'

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
	}
}
```





### Resource controllers

A resource controller groups the different actions required to handle a resource in a
[RESTful][] fashion. It is created by extending the [Controller][] class and
using [ActionTrait][].

The following table list the verbs/routes and their corresponding action. `{name}` is the
placeholder for the plural name of the resource, while `{id}` is the placeholder for the
resource identifier.

| HTTP verb | Path                | Action  | Used for                                   |
| --------- | ------------------- | ------- | ------------------------------------------ |
| GET       | `/{name}`           | index   | A list of `{resource}`                     |
| GET       | `/{name}/new`       | new     | A form for creating a new `{resource}`     |
| POST      | `/{name}`           | create  | Create a new `{resource}`                  |
| GET       | `/{name}/{id}`      | show    | A specific `{resource}`                    |
| GET       | `/{name}/{id}/edit` | edit    | A form for editing a specific `{resource}` |
| PATCH/PUT | `/{name}/{id}`      | update  | Update a specific `{resource}`             |
| DELETE    | `/{name}/{id}`      | delete  | Delete a specific `{resource}`             |

The routes listed are more of a guideline than a requirement, still the actions are important.

The following example demonstrates how the resource controller for _articles_ may be
implemented. The example implements all actions, but you are free to implement only
some of them.

```php
<?php

use ICanBoogie\Routing\Controller;

class PhotosController extends Controller
{
	use Controller\ActionTrait;

	protected function action_index()
	{
		// …
	}
	
	protected function action_new()
	{
		// …
	}
	
	protected function action_create()
	{
		// …
	}
	
	protected function action_show($id)
	{
		// …
	}
	
	protected function action_edit($id)
	{
		// …
	}
	
	protected function action_update($id)
	{
		// …
	}

	protected function action_delete($id)
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

// only create the _index_ definition
$definitions = Make::resource('articles', ArticlesController::class, [

	Make::OPTION_ONLY => Make::ACTION_INDEX

]);

// only create the _index_ and _show_ definitions
$definitions = Make::resource('articles', ArticlesController::class, [

	Make::OPTION_ONLY => [ Make::ACTION_INDEX, Make::ACTION_SHOW ]

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





## Requirements

The package requires PHP 5.6 or later.





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
[documentation][]. You can generate the documentation for the package and its dependencies with the `make doc` command. The documentation is generated in the `build/docs` directory. [ApiGen](http://apigen.org/) is required. The directory can later be cleaned with the `make clean` command.





## Testing

The test suite is ran with the `make test` command. [PHPUnit](https://phpunit.de/) and [Composer](http://getcomposer.org/) need to be globally available to run the suite. The command installs dependencies as required. The `make test-coverage` command runs test suite and also creates an HTML coverage report in `build/coverage`. The directory can later be cleaned with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://img.shields.io/travis/ICanBoogie/Routing.svg)](http://travis-ci.org/ICanBoogie/Routing)
[![Code Coverage](https://img.shields.io/coveralls/ICanBoogie/Routing.svg)](https://coveralls.io/r/ICanBoogie/Routing)





## License

**icanboogie/routing** is licensed under the New BSD License - See the [LICENSE](LICENSE) file for details.





[ControllerBindings]:                  https://icanboogie.org/api/bind-routing/0.2/class-ICanBoogie.Binding.Routing.ControllerBindings.html
[Response]:                            https://icanboogie.org/api/http/3.0/class-ICanBoogie.HTTP.Response.html
[Request]:                             https://icanboogie.org/api/http/3.0/class-ICanBoogie.HTTP.Request.html
[RequestDispatcher]:                   https://icanboogie.org/api/http/3.0/class-ICanBoogie.HTTP.RequestDispatcher.html
[documentation]:                       https://icanboogie.org/api/routing/4.0/
[ActionNotDefined]:                    https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.ActionNotDefined.html
[ActionTrait]:                         https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Controller.ActionTrait.html
[Controller]:                          https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Controller.html
[Controller\BeforeActionEvent]:        https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Controller.BeforeActionEvent.html
[Controller\ActionEvent]:              https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Controller.ActionEvent.html
[ControllerNotDefined]:                https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.ControllerNotDefined.html
[FormattedRoute]:                      https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.FormattedRoute.html
[Pattern]:                             https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Pattern.html
[PatternNotDefined]:                   https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.PatternNotDefined.html
[ResourceTrait]:                       https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Controller.ResourceTrait.html
[Route]:                               https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Route.html
[Route\RescueEvent]:                   https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.Route.RescueEvent.html
[RouteCollection]:                     https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteCollection.html
[RouteDispatcher]:                     https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteDispatcher.html
[RouteDispatcher\BeforeDispatchEvent]: https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteDispatcher.BeforeDispatchEvent.html
[RouteDispatcher\DispatchEvent]:       https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteDispatcher.DispatchEvent.html
[RouteMaker]:                          https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteMaker.html
[RouteNotDefined]:                     https://icanboogie.org/api/routing/4.0/class-ICanBoogie.Routing.RouteNotDefined.html
[ICanBoogie]:                          https://github.com/ICanBoogie/ICanBoogie
[icanboogie/bind-routing]:             https://github.com/ICanBoogie/bind-routing
[icanboogie/service]:                  https://github.com/ICanBoogie/service
[icanboogie/view]:                     https://github.com/ICanBoogie/View
[RESTful]:                             https://en.wikipedia.org/wiki/Representational_state_transfer
