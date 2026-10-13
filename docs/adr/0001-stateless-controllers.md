# 1. Stateless controllers

- Status: Proposed
- Date: 2026-10-12
- Deciders: Olivier Laviale

## Context

Today, a controller is both application code and a `Responder`. `ControllerAbstract` implements
`Responder`, stores the `Request`, `Route`, and `Response` on `$this`, and dispatches to an action
method found through a naming-convention lookup (`{method}_articles_show` down to `show`). Path
values are passed to the action by position, in the order they appear in the pattern.

This merge of roles causes most of the friction in the current design:

- The controller keeps per-request state, so it must be instantiated once per request. The
  documentation has to warn about it twice, including a note about factory services in the
  container. Registering a controller as a shared service leaks state across requests in
  long-running runtimes (FrankenPHP worker mode, RoadRunner, Swoole).
- Positional binding is fragile. Changing `/:year/:slug` to `/:slug/:year` silently passes the wrong
  values to every action.
- The method lookup is invisible to static analysis and IDEs, which is why private actions need an
  `@uses` hint.
- A controller needs a base class to get the `respond()` plumbing, and it does its own dispatch
  because nothing outside it does that job.

`Responder` is the only contract the router knows:

```php
interface Responder
{
    /**
     * @throws Throwable if the response can't be produced.
     */
    public function respond(Request $request): Response;
}
```

## Decision drivers

### Correctness

- **Argument binding must survive route changes.** Reordering or renaming placeholders must not
  silently pass wrong values to an action. This requires name-based, typed binding.
- **Fail early.** Wiring mistakes (unknown parameter, unresolvable type, placeholder missing from
  the pattern) should fail when routes are collected, not at request time.
- **Safe in long-running runtimes.** Controllers must not carry per-request state, so they can be
  shared across requests.

### Architecture

- **`Responder` stays the only contract the router knows.** The routing boundary remains opaque, and
  new features are adapters behind it.
- **Autonomy from the ICanBoogie framework.** Attribute routing must work without framework
  bootstrapping and without depending on any container. A controller is obtained through a closure,
  so the application decides how to build it. bind-routing becomes a thin adapter.
- **Clear dependency rule.** Services arrive through the constructor, request-derived values through
  method parameters, and application code does no service location.
- **Separation of behavior and HTTP translation.** Controllers hold behavior, one generic responder
  holds the plumbing.

### Developer experience

- **Static analysis and IDE support.** Entry points are visible to PHPStan and IDEs: no
  naming-convention dispatch, no `@uses` workarounds.
- **Testability.** Controller methods can be called with plain values, and responders can be built
  with closures instead of a container.
- **Explicit over conventional.** An action is tied to a method by an attribute, not by a lookup
  chain.

### Operations

- **No reflection on the hot path.** Production reads precomputed plans from an opcache-friendly
  cache.
- **Minimal required dependencies.** The attribute collector is optional. The inflector, which
  infers action identifiers, is required.

### Project constraints

- **Backward compatibility.** `ControllerAbstract`, `ActionTrait`, and existing bind-routing
  applications keep working through 7.x, with a deprecation path rather than a break.
- **Maintenance cost.** Prefer data caches over code generation, and fewer moving parts over maximum
  speed. Performance claims follow [PERFORMANCE.md](../../PERFORMANCE.md) and need a measured win.

### Tensions

- Backward compatibility versus simplicity: keeping `ControllerAbstract` means two ways of writing
  controllers for a while.
- Build-time caching versus development ergonomics: a cache-invalidation story, or a no-cache mode
  in development, is required.
- Explicit attributes versus the convenience of inferred action names: inference is kept (an omitted
  `action:` is derived from the controller's and the method's names), and an explicit `action:`
  wins.

## Considered options

### A. Status quo

Keep `ControllerAbstract` as the way to write controllers.

- Good: no work, no migration.
- Bad: fails the long-running-runtime, binding, static-analysis, and testability drivers.

### B. Resolve arguments inside `ControllerAbstract`

`ControllerAbstract::respond()` resolves the arguments of `action()` itself.

- Good: fixes positional binding without a new layer.
- Bad: the controller still stores the request, so it is still stateful and still one instance per
  request. The resolver has to reach every controller through constructor injection (boilerplate in
  every subclass) or a static/global lookup.

### C. Generic `MethodResponder` with build-time parameter plans

**Controllers are plain classes.** A constructor for services, public methods for actions. No base
class, no interface, no request on `$this`. Controllers written this way are never `Responder`s
themselves.

```php
final class ArticleController
{
    public function __construct(private ArticleRepository $articles) {}

    #[Get('/articles/:id')]
    public function show(int $id): Response
    {
        // ...
    }
}
```

**`MethodResponder` is a `Responder`, one per controller class.** It is provided by the library and
configured with data. At runtime it finds the method to call from the `Route` that
`RequestResponderProvider` already puts in the request context, which is how `ActionTrait`
dispatches today, moved outside the controller:

```php
final class MethodResponder implements Responder
{
    /**
     * @param Closure(): object $controller
     * @param array<string, ActionDefinition> $actions
     *     Where _key_ is an action, and _value_ the method to call and its parameter plan.
     */
    public function __construct(
        private Closure $controller,
        private array $actions,
    ) {}

    public function respond(Request $request): Response
    {
        $action = $this->actions[$request->context->get(Route::class)->action];
        $args = [];

        foreach ($action->plan as $parameter) {
            $args[] = $parameter->resolve($request);
        }

        $result = ($this->controller)()->{$action->method}(...$args);

        return $result instanceof Response ? $result : $result->into_response($request);
    }
}
```

- `MethodResponder` is a `Responder` like any other, so it can be decorated, wrapped by middleware,
  and chained by providers. The contract stays `Request => Response`.
- **Actions return `Response|IntoResponse`.** Inspired by Axum's `IntoResponse`, the returned object
  knows how to turn itself into a response:

  ```php
  interface IntoResponse
  {
      public function into_response(Request $request): Response;
  }
  ```

  There is no registry and no converter dependency: conversion lives in the returned object, and
  `MethodResponder` only gains one branch. There are no `string`, `Stringable`, or `null` shortcuts,
  and no mutate-then-return-`null` path; anything else is a `TypeError`. The declared return type is
  checked when the config is built: `Response` or a class implementing `IntoResponse` is accepted,
  anything else is rejected at configuration time rather than on a request.
- Wrapper types make the interface useful without making domain objects know about HTTP, e.g. a
  `View` (see below) or a `Json($data)`. Returning a bare domain object (`Article`) would require a
  converter registry, which is left to a future ADR.
- `IntoResponse` is defined in this library for now. It could move to `icanboogie/http` next to
  `Response` if other packages need it.
- `ActionResponderProvider::responder_for_action()` returns the `MethodResponder` of the controller
  that owns the action, created lazily. Dispatch is not duplicated: the router has already matched
  the route, and `MethodResponder` only reads it. It does depend on the `Route` being in the request
  context, which `RequestResponderProvider` guarantees and tests must replicate.
- The controller is obtained through a closure, built by the application's wiring (the one piece
  that legitimately knows how controllers are made), e.g. with a container:
  `fn() => $container->get(ArticleController::class)`. Tests pass
  `fn() => new ArticleController($fakeRepository)`. Shared versus fresh instances is the closure's
  concern, and neither `MethodResponder` nor `Config` depends on a container or on PSR-11.
- The **parameter plan** (see below) is the result of analyzing a method signature once, at
  configuration time, so that no reflection happens at runtime.
- Failures caused by client input are typed exceptions mapped to responses in one place:
  - **404 Not Found** when a path value fails conversion (`/articles/abc` for an `int $id`): the
    resource designated by the URL doesn't exist.
  - **400 Bad Request** when a query value or part of the body fails conversion, or a required value
    is missing.
  - A missing value with a default uses the default.
  - Wiring mistakes are not client input: they are detected at configuration time, and a plan that
    is inconsistent at runtime is a server error (500), not a 4xx.

  The exceptions build on the `ICanBoogie\HTTP` hierarchy (`NotFound`, `BadRequest`) and implement
  `ICanBoogie\Routing\Exception`, as `Exception\RouteNotFound` does, so applications can catch them
  by status or by library.
- Rule: **services go through the constructor, request-derived values through method parameters.**
  Parameters are never services.

#### Parameter plan

The plan of an action's method is a `list<Parameter>`: one entry per method parameter, in order. A
`Parameter` is a readonly object with `__set_state`, so the plan is exported with the rest of
`Config`. It is also the runtime resolver: it knows how to produce its value from the request, so
`MethodResponder` only loops over the entries and there is no resolver registry or second lookup.

```php
interface Parameter
{
    public function resolve(Request $request): mixed;
}
```

At configuration time, each reflected parameter is offered, in order, to the built-in claimants: by
type (`Request`, `Route`), by attribute (`#[Path]`, `#[Query]`, `#[Part]`, `#[Body]`), then as an
implicit path parameter. The first one that returns a `Parameter` owns it. If none does, the config
build fails, naming the class, method, parameter and pattern, e.g. "`SkillController::show()`
parameter `$slug`: no `:slug` in `/skills/:id`."

Parameters are bound to the part of the request they come from, which is explicit in the signature.
This matters for safety too: `Request::$params` merges path, body, and query values, with the
earlier winning, so reading the merged array for a body value would let `?role=admin` fill in what
the body left out.

| Attribute | Reads | Type decides |
|---|---|---|
| `#[Path]` | `$request->path_params` | scalar or backed enum |
| `#[Query]` | `$request->query_params` | scalar or backed enum |
| `#[Part]` | a named piece of the body (`request_params`) | scalar or backed enum; files later |
| `#[Body]` | the raw body, as a stream | a stream resource (`@param resource`) |

- `Request` and `Route` parameters are claimed by type, with no attribute.
- A scalar or enum parameter with no attribute is a `#[Path]` parameter: path is the one source the
  pattern declares, and the build verifies it. `#[Query]`, `#[Part]`, and `#[Body]` are always
  explicit, so a missing placeholder is a build error, never a silent fallback to the query string
  or the body.
- The key defaults to the parameter name; `#[Query('q')] string $search` overrides it.
- `#[Part]` names a piece of the body, as a part of `multipart/form-data` has a name. It reads
  `request_params`, which `Request` fills with `$_POST` or, for `application/json`, the decoded JSON
  object, so it does not care about the encoding. The names `#[Get]` and `#[Post]` would echo
  `$_GET` and `$_POST`, but they are taken by the route shortcuts, and the source is not tied to the
  HTTP method: a `#[Put]` route reads its form data or JSON from the same `request_params`.
- `#[Body]` is the raw body as a stream, a file pointer such as `fopen('php://input', 'r')`. Decoded
  data is already available through `#[Part]`: PHP decodes `application/x-www-form-urlencoded` and
  `multipart/form-data`, and `Request` decodes `application/json`, all into `request_params`.
  `#[Body]` covers what `#[Part]` cannot, e.g. XML, a custom format, or a large upload to process
  without loading it in memory. At most one `#[Body]` per method is allowed.
  - PHP has no `resource` type declaration, and no stream abstraction is imposed (no PSR-7
    `StreamInterface`). `#[Body]` is the one parameter whose declaration is `mixed` or absent,
    documented with `@param resource $body`; the build rejects any other declared type.
  - `php://input` is empty for `multipart/form-data`, because PHP consumes the body. `#[Body]` is
    meant for the other content types, and multipart goes through `#[Part]`.
  - `Request` must expose the stream, opened lazily; tests can inject any stream (`php://memory`).
    This needs a change in `icanboogie/http`.
- **No hydration into classes.** `#[Part]` and `#[Body]` never produce classes: a class-typed
  parameter is rejected at configuration time. The library cannot impose a serialization package
  (PHP has no serde: Symfony Serializer, Valinor, JMS, and hand-written mapping all exist), and
  hydration needs a service, which a plan entry cannot hold. It follows the dependency rule instead:
  the controller reads the values it needs and passes them to a mapper or serializer injected
  through its constructor. Axum offers `Json<T>` because serde is the Rust standard; its other
  extension point, the `FromRequest` trait, is the model for a possible future opt-in contract (a
  class that builds itself from the request, symmetrical with `IntoResponse`), which would need its
  own ADR.
- Path values are read from `$request->path_params`, set by `RequestResponderProvider`. They are
  strings and are cast to the declared type: `int`, `float`, `string`, `bool`, and backed enums.
  Union types, intersection types, `array`, and untyped parameters are rejected at configuration
  time.
- Casting is explicit, not PHP's parameter coercion, and a failure is a client error:
  - `int`: an optional `-` followed by decimal digits, within the integer range. Leading zeros are
    accepted (`007` is `7`), so the check is a pattern and a range test, not `FILTER_VALIDATE_INT`,
    which rejects them. As a consequence `/articles/7` and `/articles/007` reach the same action;
    applications that care about canonical URLs can redirect.
  - `float`: `is_numeric` and finite.
  - `bool`: `1`, `0`, `true`, or `false`.
  - backed enums: `tryFrom`, with int-backed enums cast first.
  - `string`: unchanged.
- A path parameter must name a placeholder in `Pattern::$params` of **every** route of the method (a
  method may carry several `#[Route]` attributes), unless it is nullable or has a default. At
  runtime, a parameter whose placeholder is not in the matched route gets its default, or `null`.
  For example, `list(?int $page = null)` can serve both `/articles` and `/articles/:page`, whereas
  `list(int $page)` on both routes fails at configuration time instead of on the first request to
  `/articles`. It must be a placeholder of at least one route even when it has a fallback, otherwise
  a typo would leave it silently unbound. Anonymous placeholders (`<\d+>`) have no name and cannot
  be bound. The catch-all `*` is named `all`.
- Defaults are always exportable: the supported types are scalars and backed enums, so a default is
  a scalar, `null`, or an enum case. Any other type is rejected, which also rules out `new` in an
  initializer.
- The same analysis checks the method: it must be public, non-static and non-abstract, and declare a
  return type that is `Response`, or a class implementing `IntoResponse` (a union of those is
  accepted; `static` and `self` are resolved against the declaring class; nullable types, `void`,
  `mixed`, and anything else are rejected). Variadic parameters are rejected. All of these are
  `ConfigNotValid` errors, naming the class, the method, and the parameter.
- Services are not parameters. Plan entries are plain data objects that resolve themselves from the
  request and hold no services, which keeps the plan exportable and the dependency rule simple. A
  value that needs a service (an authenticated user, a record to load) is obtained by the controller
  from a service injected through its constructor, using the `Request` or a path value as input. A
  runtime resolver registry that could hold services is not planned.

Slices: first, `Request`, `Route`, and path parameters; second, `#[Path]`, `#[Query]`, and `#[Part]`
as single values; later, `#[Body]` as a stream, once `Request` exposes the raw body.

#### Configuration

Attributes are first class. `Route` and its shortcuts (`Get`, `Post`, `Put`, `Patch`, `Delete`,
`Head`, `Options`, `Connect`, `Trace`) are ported from bind-routing into this library, as
method-level attributes. The prefixes shared by a controller's methods are declared by a class-level
`#[Prefix]` (see below). Discovery with
[olvlvl/composer-attribute-collector](https://github.com/olvlvl/composer-attribute-collector) is
optional; the explicit API works without it.

The builder follows `ActiveRecord\ConfigBuilder::add_record()`: it validates and resolves everything
while it runs, and `build()` returns a `final readonly class Config` of plain definitions with
`__set_state`, so it can be exported with `var_export` and loaded on subsequent requests at no cost.

```php
$builder->add_responder(
    ArticleController::class,   // attributes on the class and its methods are read here
    route_builder: fn(RouteBuilder $r) => $r   // optional, for people not using attributes
        ->get('/articles/:id', 'show')
        ->post('/articles', 'create'),
);
```

`add_responder()` reflects the class once, merges attribute routes with those from the
`RouteBuilder`, computes the action identifiers (an explicit `action:` is used as-is; when it is
omitted, it is inferred from the controller's name and the method's name, which is used as is, e.g.
`PhotosController::show` is `photos:show`), computes each method's parameter plan, and validates it
against the patterns. Mistakes fail there with a clear message, and nothing is registered when a
controller is not valid. `use_attributes()`-style discovery is a loop that calls `add_responder()`.

A route exists because a controller defines it, so `add_responder()` is the only way to add routes
to the `ConfigBuilder`, which doesn't build on `RouteCollector`. The collector and `RouteMaker` are
marked obsolete: they work the other way around, creating routes that nothing might answer,
`resource()` being the clearest case. Routes can still be created by hand with
`RouteProvider\Mutable` and `RouteProvider\Immutable`. Responders that are not method-based (a
`ControllerAbstract` subclass, a hand-written `Responder`) are registered with `add_responder()`
too, and define their routes the same way.

`Config` holds a `RouteProvider\Compiled` for the routes, plus the data that bind-routing currently
keeps in the Symfony container: which classes are responders (the `action_responder` tag), and which
action maps to which controller (the `routing.action_responder.aliases` parameter). Here that is
`array<class-string, ControllerDefinition>`, each with its `ActionDefinition`s (method and plan).
The action identifier is computed once, in `add_responder()`, so routes and responders can't
disagree. The container only needs to know how to produce a controller instance: bind-routing
registers each class as a shared service from `Config` and feeds the provider's factory closure.

Like `ActiveRecord\Config`, `Config` and its builder belong to this library and do not depend on
`icanboogie/config`; bind-routing only adapts the builder to that package, and keeps registering the
controllers in the container from `Config`.

In production the configuration is exported and `Config` is loaded as-is. In development nothing is
exported: the configuration is built on each request, and routes are served through
`RouteProvider\Memoize`, which indexes them in memory. There is no cache to invalidate.

A class that already implements `Responder` (for instance a `ControllerAbstract` subclass) can still
be registered; it is used as-is, with no `MethodResponder` and no plan.

Good: satisfies every driver; the router, providers, and middleware still see only a `Responder`;
closures, `ControllerAbstract` subclasses, and hand-written responders keep working side by side.
Bad: two ways to write controllers during the transition; a build step and cache to maintain.

#### Prefixes

Controllers often belong together. In one application, the cart is split into three controllers,
each responsible for an aspect of it, under `Presentation/HTTP/Controllers/Frontend/Cart`. They all
share the pattern prefix `/cart`, and their actions are `cart:photos:show`, where `photos:show` is
inferred from the controller and the method, and `cart` is shared.

A class-level `#[Prefix]` declares both:

```php
#[Prefix(route: '/cart', action: 'cart')]
final class PhotosController
{
    #[Get('/photos/:id')]            // pattern /cart/photos/:id, action cart:photos:show
    public function show(int $id): Response
    {
        // ...
    }
}
```

- `Prefix` is class-level only. Both arguments are optional, and at least one is required.
- `route:` is concatenated before each method's pattern. It may contain placeholders, which then
  become path parameters of every method of the class.
- `action:` is concatenated before each action, inferred or explicit, with `:` added automatically.
- `Route` is method-level only. The class-level `#[Route('/cart')]` of bind-routing becomes
  `#[Prefix(route: '/cart')]`. An empty method pattern is allowed when the class has a
  `Prefix(route:)`.
- `Prefix` is not final. An application can extend it with fixed values (a `#[CartPrefix]`) to avoid
  repeating them on each controller; discovery by instance-of still finds it.
- Prefixes are plain concatenation, so they stay predictable. Deriving them from the PHP namespace
  or directory was rejected: moving a class would silently change action ids, which are public
  identifiers. A group argument on `add_responder()` was rejected because attribute discovery has no
  group to attach it to.
- Route ids (`id:`) are not prefixed.
- `RouteBuilder::prefix(route:, action:)` is the equivalent of `#[Prefix]` for controllers
  registered without attributes, with the same argument names and the same concatenation rules:

  ```php
  $builder->add_responder(
      PhotosController::class,
      route_builder: fn(RouteBuilder $r) => $r
          ->prefix(route: '/cart', action: 'cart')
          ->get('/photos/:id', 'show'),   // /cart/photos/:id, action cart:photos:show
  );
  ```

  It applies to every route of the builder wherever the call sits, and may be called once. Defining
  a prefix with both the attribute and `prefix()` for the same class is a configuration error. A
  shared closure or function can apply the same prefix to several controllers.

### D. C plus generated code per action

Same as C, but a specialized PHP class per action is generated with argument extraction inlined.

- Good: removes a loop and some lookups per request.
- Bad: a code generator to maintain, generated code to debug, cache invalidation for code instead of
  data. The gain is negligible next to the rest of a request. Reconsider only if `make benchmark`
  shows a real cost.

## Decision

Adopt **option C**.

`Responder` remains the contract the router knows. Controllers become plain classes with a
constructor for services and public methods for actions. A generic `MethodResponder`, one per
controller class, finds the method from the `Route` in the request context, builds the arguments
from a parameter plan computed once when the configuration is built, and returns the `Response`, or
the `IntoResponse` it converts. Parameters are bound to the part of the request they come from
(`#[Path]`, `#[Query]`, `#[Part]`, `#[Body]`), by name and type, and client input errors are 404 for
path values and 400 otherwise. Routes, responders, and plans are produced by a builder, in the style
of `ActiveRecord\ConfigBuilder::add_record()`, into a readonly, exportable `Config`.

`ControllerAbstract` remains as the low-level, stateful option for full control of the request and
response. Both ship side by side in 7.x through the `ActionResponderProvider` chain.

## Consequences

- Controllers become ordinary services that can be shared and constructor-injected, safely, in
  long-running runtimes.
- Controllers lose the `string` return convenience of `ControllerAbstract`; they return a `Response`
  or an `IntoResponse` such as a `View`.
- **icanboogie/view** needs rework to be used from plain controllers. `RenderTrait` and
  `ViewProvider::view_for_controller()` rely on `ControllerAbstract`. A `View` would implement
  `IntoResponse`, be created per call by a factory injected through the constructor (it is mutable,
  so it must not be shared), and resolve its default template and layout from the `Route` in the
  request context when `into_response()` is called. `RenderTrait` stays for legacy controllers. This
  is a follow-up in `icanboogie/view` and `bind-view`.
- "Controller" stops being a type in the library; it is a role any object with attributed methods
  can play. The library's types are `Responder`, `IntoResponse`, `MethodResponder`, `Parameter`, the
  attributes, and the builder and `Config`.
- A readonly, exportable `Config` is produced once by `ConfigBuilder::build()`; subsequent requests
  load it without the cost of reflecting or building. It absorbs data bind-routing keeps in the
  Symfony container (responder tag, action aliases), which shrinks to registering the controller
  classes listed in `Config` as shared services.
- `MethodResponder` depends on the matched `Route` being in the request context.
- Route patterns and path parameter capture are unchanged. A pattern works in both directions
  (`matches()` and `format()`), so URL generation keeps working, and the name of a path parameter in
  an action's signature is the same name used to format the URL. The plan reads `Pattern::$params`
  and does not introduce a second syntax.
- Wiring mistakes are reported at collection time with clear messages, e.g.
  "`SkillController::show()` parameter `$slug`: no `:slug` in `/skills/:id`."
- Plans are inspectable and testable: a console command can print what each action consumes, and
  plans plus routes are most of what an OpenAPI document needs.
- Action methods must be public: they are entry points called from outside, and `private` plus
  `@uses` fights the tooling. The builder rejects non-public attributed methods.
- `Config` is exported in production only. Development builds it on each request, with the routes
  served through `Memoize`, so there is nothing to invalidate.
- Documentation (`Controllers.md`, `ActionControllers.md`, `ActionResponders.md`) and `CHANGELOG.md`
  need updating once implemented, and `RouteProvider\Compiled` must stay in sync if `Route` or
  `Pattern` change.
- `ControllerAbstract` and `ActionTrait` get a deprecation path in a later major version.

## Rollout

1. **Slice one**, in this library: `Parameter` (`Request`, `Route`, path), `IntoResponse`,
   `MethodResponder`, `ActionDefinition`/`ControllerDefinition`, the attributes ported from
   bind-routing, the builder with `add_responder()` and `RouteBuilder`, `Config`, and an
   `ActionResponderProvider` built from `Config`.
2. **Slice two**: `#[Path]`, `#[Query]`, `#[Part]`.
3. **Other packages**, tracked separately: bind-routing (thin `Builder` adapter, controller
   registration from `Config`, attributes re-exported or deprecated in favor of this library's);
   `icanboogie/view` and `bind-view` (`IntoResponse`, route-based templates); `icanboogie/http` (raw
   body, needed by `#[Body]`).
4. **Later**: `#[Body]`, optional pattern segments, converters for bare return objects, events
   versus middleware, each with its own ADR.

Each step is a commit with its tests and its `CHANGELOG.md` entry. The user documentation
(`Controllers.md`, `ActionControllers.md`, `ActionResponders.md`, and a new page) is written once
the API is usable and stable, with the provider built from `Config`, so that it is not rewritten
each time a name changes; the code is documented with PHPDoc as it goes. These changes are justified
by correctness and architecture rather than speed, so they are not `PERF` commits, but
`make benchmark` is run before and after to confirm that `MethodResponder` is no slower than
`ControllerAbstract` in dispatch. `ControllerAbstract` and `ActionTrait` stay through 7.x; their
deprecation is announced in the documentation once the new path is released, and their removal
belongs to a later major version.

## Out of scope

- Optional segments in route patterns, e.g. `/my/path[/optional]`. Patterns have no notion of
  optional today; the workaround is several routes on the same method (`/articles` and
  `/articles/:page`). The plan already composes with the improvement: a path parameter whose
  placeholder is optional would simply have to be nullable or have a default, as the "every pattern"
  rule requires for multi-route methods. The change itself touches `Pattern` (parsing, `matches()`,
  `format()` omitting absent optional parts) and `RouteProvider\Compiled` (its chunked matching
  regex, with the branch reset group and `(*:N)` marks, and its exported state), so it deserves its
  own ADR and benchmark.
- Replacing `BeforeActionEvent` / `ActionEvent` with middleware, or the reverse. These overlap, but
  it is a separate decision for its own ADR.
- Value objects as scalar parameters (`Id $id`, `Slug $slug`). A `#[Parse]` attribute on a public
  static method of the class would mark its factory:

  ```php
  final class ArticleId
  {
      #[Parse]
      public static function from_string(string $value): self { /* ... */ }
  }
  ```

  The build verifies the method (public, static, one `string` parameter, returns `self` or `static`)
  and stores `[class, method]` in the plan, so nothing is reflected at runtime. The invalid-value
  failure maps like any other cast: 404 for a path value, 400 otherwise. Alternatives were rejected:
  a `from()` convention would match unrelated factories such as `Pattern::from()` and
  `Request::from()`; an interface (the counterpart of `ToSlug`) couples the class to the library; a
  closure in the attribute is not exportable, and only first-class callables are accepted in
  attributes, which needs PHP 8.5. Third-party classes cannot be annotated; the controller builds
  them from a `string`.
- Hydrating parameters into classes (`#[Part]`, `#[Body]`), with or without validation. The options
  are an opt-in contract implemented by the class (like `FromRequest` in Axum), or a hydrator
  service, which would require a service-holding parameter. Either needs its own ADR, and the status
  for a validation failure (400 or 422) and for an unsupported `Content-Type` (415) are decided
  there.
- Returning bare domain objects (DTOs, entities) or `string`. That would need a converter registry
  claiming return types at configuration time, the way parameters are claimed. It is a possible
  extension of the same plan, defined by its own ADR if the need arises.

## Open questions

- `icanboogie/http` must expose the raw body as a stream for `#[Body]`, opened lazily and injectable
  in tests. `Request::from_server()` currently reads `php://input` only for JSON and discards it.
  The details (when the stream is opened, who closes it, how it relates to the JSON already decoded
  into `request_params`) belong to that change.

- Route cache strategy. `build()` always compiles the routes, so development, which builds the
  configuration on each request and would rather use `Memoize`, has no way to choose. The idea is a
  `CacheStrategy` enum (`NONE`, `MEMOIZE`, `COMPILE`) set with `ConfigBuilder::cache_strategy()`,
  `COMPILE` being the default. `Memoize` can't be exported as is: its lazy indexes are exported
  once initialized and have no `__set_state()`. So `Config` would keep the undecorated provider
  and the strategy, and apply the decoration when it is constructed or loaded
  (`Config::__set_state()`), which keeps the exported data stable and lets the same file serve
  development and production. The alternative is to document `MEMOIZE` as not exportable.
