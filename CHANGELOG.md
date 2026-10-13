# CHANGELOG

## v7.0

### New Requirements

PHP 8.4+

[icanboogie/inflector](https://github.com/ICanBoogie/Inflector), to infer the actions of controllers.

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
  The building blocks are in place:
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
      defines the prefixes shared by its methods: `#[Prefix(pattern: '/cart', action: 'cart')]` turns
      the route `/photos/:id` with the action `photos:show` into `/cart/photos/:id` and
      `cart:photos:show`. The pattern of a shortcut is optional, it can be left empty when the
      `Prefix` defines the pattern.
    - `PlanBuilder` analyzes a method with reflection, once, to build the plan of its parameters:
      a parameter typed `Request` or `Route` receives them, any other parameter is a path parameter
      found by its name in the patterns of the routes, so reordering placeholders can't pass the
      wrong values. It also checks that the method is public and returns a `Response` or an
      `IntoResponse`. Mistakes throw `ConfigNotValid` with the class, the method, and the parameter,
      e.g. "`SkillController::show()` parameter `$slug`: no `:slug` in `/skills/:id`.", instead of
      failing when a request is handled.
    - `ConfigBuilder::add_controller()` adds a controller: it reads the attributes of its methods,
      applies the `Prefix`, infers the actions that are not specified, builds the plan of each
      method, and defines the routes. Everything is validated right away, and nothing is registered
      when the controller is not valid, for instance when the `id` of a route is used by another route
      or is the action of another. The action is inferred from the name of the
      controller and the name of the method, used as is: `PhotosController::show` is `photos:show`,
      see `ActionResolver`.
    - `RouteBuilder` defines the routes and the prefix of a controller without attributes:
      `add_controller(PhotosController::class, fn(RouteBuilder $r) => $r->prefix(pattern: '/cart',
      action: 'cart')->get('/photos/:id', 'show'))`.
    - `ConfigBuilder::build()` returns a `Config`, a readonly object that holds the routes, and the
      controllers with their actions. It can be exported with `var_export()` and cached as a PHP
      file, so subsequent requests don't pay for reflecting or building it.
    - `Query` and `Part` provide a value of the query string, or of the body, cast to the type of the
      parameter. They are bound with the attributes `#[Query]` and `#[Part]` (a form field, or a
      property of a JSON object), which take the name of the value when it is not the name of the
      parameter: `#[Query('q')] string $search`. A missing value is the default, `null` if the
      parameter is nullable, otherwise `ParameterNotValid` is thrown, as it is for a value that
      can't be cast. `ParameterNotValid` is a `ClientError`, a 400. `#[Path]` is optional, it names
      the placeholder when it is not the name of the parameter.
    - Decoders turn a value of the path, the query string, or the body into an object, see
      [docs/adr/0002-decoders.md](docs/adr/0002-decoders.md). A decoder is a class that implements
      `Decoder`, with a static `decode(EncodedValue): object` method, and throws `NotDecodable` to
      reject a value. It is bound with `#[Path(using: SlugDecoder::class)]`, and likewise for `#[Query]`
      and `#[Part]`; `using` is optional when the type of the parameter is a decoder itself. The
      `Decoded` parameter reports a value that can't be decoded like one that can't be cast, and
      `PlanBuilder` checks the decoder and its return type when the configuration is built.
    - `Body` provides the raw body of the request, the `SplFileObject` found in
      `Request::$body_stream`, which `Request::from_server()` opens on `php://input`. It is bound with
      `#[Body]`, for what `#[Part]` can't provide, e.g. XML or a large upload. With a decoder,
      `#[Body(using: ImportDecoder::class)]` is the object the decoder makes from the file object. A
      method has at most one `#[Body]`.
    - `ActionResponderProvider\FromConfig` provides the responders of the controllers of a `Config`:
      one `MethodResponder` per controller, created when one of its actions is first requested. The
      controllers are obtained through a closure, which is where services are wired. Use it with
      `RequestResponderProvider` and `Config::$routes`.
    - `ConfigBuilder::cache_strategy()` defines how the routes of the `Config` are served, with
      `CacheStrategy`: `NONE` (default) tests the routes one after the other; `MEMOIZE` uses
      `RouteProvider\Memoize`, meant for development, where the configuration is built on each
      request, the resulting `Config` can't be exported; `COMPILE` uses `RouteProvider\Compiled`,
      meant for production.
    - `ConfigBuilder::discover_controllers()` adds the controllers found through the `Route`
      attributes of their methods, so they don't have to be listed. Discovery relies on
      [olvlvl/composer-attribute-collector](https://github.com/olvlvl/composer-attribute-collector),
      which the application decides to require; it is not a requirement of the library. Controllers
      already added with `add_controller()` are skipped.
- Added `IntoResponse\Json`, an `IntoResponse` that encodes data as a JSON response. Arrays and scalars
  are encoded as they are, objects, even nested, must implement `JsonSerializable` so that nothing is
  exposed by accident. A list is encoded item by item. Use `encode` to plug a
  serializer, and `tweak` to alter the response (e.g. `Cache-Control`), see
  [docs/StatelessControllers.md](docs/StatelessControllers.md).

### Deprecated Features

- `RouteCollector`, `RouteMaker`, `RouteMaker\Options`, and `RouteMaker\Basics` are obsolete. A route
  should exist because a controller defines it, not because a collector was told to create it or a
  resource name implies routes that a controller might not implement, `resource()` being the
  clearest example. Define routes with attributes or a `RouteBuilder`, see
  `ConfigBuilder::add_controller()`.

### Backward Incompatible Changes

- `Pattern::from()` throws `InvalidPattern` when a constraint has a capturing group, e.g. `<id:(\d+)>`,
  which used to fail with a `ValueError` on the first matching request. Use `(?:...)` instead.
- `Pattern::format()` encodes values with `rawurlencode()` instead of `urlencode()`: a space is `%20`
  and a `+` is `%2B`, which is correct in a path segment.
- `Pattern::matches()` percent-decodes the captured values, so `/a/caf%C3%A9` captures `café` and
  generated URLs round-trip. Decoding happens after matching: constraints are tested against the raw
  path, and `%2F` stays inside its segment. `path_params` are therefore decoded, remove any
  `rawurldecode()` applied to them.
- `Pattern::format()` throws `PatternValueNotValid` when a value does not satisfy the constraint of
  its parameter, e.g. `'x'` for `<id:\d+>`, instead of returning a URL that no route matches. The
  encoded value is tested, like the matcher tests the path, so `<slug:[\w-]+>` rejects `café`. The
  `:name` shorthand is tested too: `[^/]+` rejects an empty value, and `:slug.html` rejects `v1.2`.
  The catch-all accepts anything.
- `ConfigBuilder::add_controller()` reports an invalid pattern as a `ConfigNotValid` that names the
  controller method and the pattern, with the `InvalidPattern` as previous exception.
- `Pattern::from()` throws `InvalidPattern` when a parameter name is used twice, e.g. `/:a/:a`; the
  last value used to win silently.
- `Pattern::format()` throws `PatternValueMissing` when a value is missing or `null`, instead of a PHP
  warning followed by a `TypeError`. A value that cannot be turned into a string throws an
  `InvalidArgumentException`.

### Other changes

- `Pattern` keeps the `*` of a catch-all pattern: `/files/*` was exposed as `/files/`, and `format()`
  ignored the `all` value. It now formats it, keeping the `/` between segments.
- Added `Pattern::captured()`, which pairs and decodes captured values. Route providers use it, so
  every one of them returns decoded `path_params`.
- `Pattern::from()` caches patterns with weak references, a pattern that nothing holds is freed instead
  of staying in memory for the life of the process.
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
