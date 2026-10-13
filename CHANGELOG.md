# CHANGELOG

## v7.0

### New Requirements

PHP 8.4+

### New features

- Added `RouteProvider\Chain`, which asks a list of route providers in turn, the first route found wins.
  Use it with a `Mutable` alongside a decorated `Immutable` to add a bit of freedom at runtime, see
  [docs/RouteProviders.md](docs/RouteProviders.md).
- Added `RouteProvider\Compiled`, a route provider meant for production that compiles another provider
  into plain data to speed up route resolution. It implements `__set_state()` like `Mutable` and
  `Immutable`, so it can be exported with `var_export()` and cached as a PHP file loaded through
  OPcache. It avoids creating routes and computing indexes on every request, which is what `Memoize`
  costs with PHP-FPM, see [docs/RouteProviders.md](docs/RouteProviders.md).
- Added stateless controllers, plain classes with a constructor for services and public methods for
  actions. They don't extend a base class and don't hold the request, so they can be shared across
  requests, see [docs/adr/0001-stateless-controllers.md](docs/adr/0001-stateless-controllers.md).
  The building blocks are in place, registering controllers is yet to come:
    - `MethodResponder` is a `Responder` that calls the method of a controller. The method is found
      with the action of the route that matched the request, and its arguments are produced by the
      `Parameter`s of its plan. The method returns a `Response`, or an `IntoResponse`, an object that
      knows how to turn itself into one; anything else is a `TypeError`.
    - `Parameter` produces the value of an action's parameter from the request. `CurrentRequest`
      provides the request, `MatchedRoute` the route, and `Path` a path parameter, found by its name
      in the pattern and not by its position, and cast to the declared type (`int`, `float`, `string`,
      `bool`, or a backed enum). A path value that can't be cast, such as `/articles/abc` for an
      `int $id`, throws `PathParameterNotValid`, which is a `NotFound`.
    - `Config\ActionDefinition` defines how to invoke an action: the method and the plan of its
      parameters. Parameters and definitions can be exported with `var_export()`.
    - Attributes define the routes of a controller, they are ported from icanboogie/bind-routing. The
      repeatable `Route` is for methods, with the shortcuts `Connect`, `Delete`, `Get`, `Head`,
      `Options`, `Patch`, `Post`, `Put`, and `Trace`. `Prefix` is for the controller class, it
      defines the prefixes shared by its methods: `#[Prefix(route: '/cart', action: 'cart')]` turns
      the route `/photos/:id` with the action `photos:show` into `/cart/photos/:id` and
      `cart:photos:show`. The pattern of a shortcut is optional, it can be left empty when the
      `Prefix` defines the route.

### Deprecated Features

None

### Backward Incompatible Changes

None

### Other changes

- `RouteNotFound` extends `NotFound`.
- Improved route resolution performance: `MemoizeByUri` finds static routes with a single array
  lookup instead of scanning them, and tests dynamic routes by chunks of about 30 with a single
  regex per chunk instead of one regex per route.



## v5.x to v6.0

### New Requirements

- PHP 8.2+

### New features

- Added the interface `ActionResponderProvider`, an equivalent of `ResponderProvider` but oriented
  towards "actions". The interface comes with complementary implementations.

- Added the interface `RouteProvider` that describes a mean to retrieve a route that matches a
  predicate. The interface comes with complementary implementations. `Immutable` can be used to create
  an immutable collection of route providers, while `Memoize` can be used to decorate a
  `RouteProvider` instance to speed up route searches. A few practical predicates come built-it, such
  as `ByAction` that matches a route with an action or `ByUri` that matches a route with a URI and
  HTTP method.

- Added implementations of the new interface `ResponderProvider` from [ICanBoogie/HTTP][]: `Chain`,
  `Container`, `Mutable`, and `Immutable`.

- Added the `InvalidPattern` exception.

### Backward Incompatible Changes

- `Controller` is now `ControllerAbstract`. `ControllerAbstract` is no longer extending `Prototyped`, required services need to be provided through the constructor.

- `Pattern:match()` is now `Pattern:matches()`.

- `UrlGenerator` now replaces everything that was related to URL formatting.

### Deprecated Features

- Following the evolution of [ICanBoogie/HTTP][], everything related to _dispatchers_ has been
  replaced with _responders_. `ClosureController` has been removed.

- All helpers have been removed.

- `RouteDefinition` is gone, routes are defined with `Route`

### Other Changes

- `AccessorTrait` usage has mostly been replaced by `readonly` properties.

- Drop Prophecy


[ICanBoogie/HTTP]: https://github.com/ICanBoogie/HTTP
