# CHANGELOG

## v7.0

### New Requirements

PHP 8.4+

### New features

- Added `RouteProvider\Compiled`, a route provider backed by plain data that can be exported to a PHP file
  and loaded through OPcache. It avoids creating routes and computing indexes on every request,
  which is what `Memoize` costs with PHP-FPM. Use `Compiled::dump()` to generate the file, see
  [docs/RouteProviders.md](docs/RouteProviders.md).

- Added `Exception\PatternMissingValue`, thrown by `Pattern::format()` when a value is missing for a
  placeholder, instead of a PHP warning followed by a `TypeError`. It extends `PatternRequiresValues`.

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
