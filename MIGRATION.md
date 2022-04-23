# Migration

## v5.0x to v6.0x

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

- `Controller` is now `ControllerAbstract`.

- `Pattern:match()` is now `Pattern:matches()`.

- `UrlGenerator` now replaces everything that was related to URL formatting.

### Deprecated Features

- Following the evolution of [ICanBoogie/HTTP][], everything related to _dispatchers_ has been
  replaced with _responders_. `ClosureController` has been removed.

- All helpers have been removed.

- `RouteDefinition` is gone, routes are defined with `Route`

### Other Changes

- Requires PHP 8.1+

- `AccessorTrait` usage has mostly been replaced by `readonly` properties.



[ICanBoogie/HTTP]: https://github.com/ICanBoogie/HTTP
