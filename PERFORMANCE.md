# Performance

Suggestions to improve the performance of the package, inspired by how
[symfony/routing](https://github.com/symfony/routing) and
[nikic/FastRoute](https://github.com/nikic/FastRoute) match and generate URLs. They are
suggestions, not commitments. None of them has been benchmarked yet; step 0 is to measure.

## Ground rules

Symfony and FastRoute are sources of ideas, not of requirements. A change is only worth making if
it passes one of these tests:

1. **Measured:** `make benchmark` shows a clear improvement on a scenario that matters, without
   regressing the others. The numbers go in the commit message.
2. **Better API:** the change makes the package easier or safer to use, even if the speed is the
   same. These belong in [SUGGESTIONS.md](SUGGESTIONS.md) or CHANGELOG.md, not under a `PERF`
   prefix.

Anything that passes neither is dropped, however well it works elsewhere.

## Triage against the baseline

What the baseline (see *Measuring*) supports, and what it does not:

|  # | Suggestion                        | Verdict                  | Why                                                                                                                                                                          |
|---:|-----------------------------------|--------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
|  1 | Static hash map                   | **Do**                   | Static lookup grows 1.1 → 52 µs from 10 to 1,000 routes; a hash lookup should be flat.                                                                                       |
|  3 | Chunked combined regex            | **Do**                   | Dynamic lookup grows 2 → 124 µs. Prototype first; keep only if it wins at 100 routes too, not just 1,000.                                                                    |
|  4 | Compiled provider                 | **Measure first**        | Cold start is 883 µs at 1,000 routes, but only matters for PHP-FPM. Add a benchmark loading the table through `__set_state()` from OPcache before deciding; adds public API. |
|  5 | Single URI parse / lazy 405       | **Do, for the API**      | Speed matters only on 404/405 paths. The real gain is `MethodNotAllowed` knowing the allowed methods (`Allow` header).                                                       |
|  9 | Partition by method               | **Only if needed**       | Unproven. Re-evaluate after 1 and 3; a hash map and chunking may already remove the cost.                                                                                    |
|  2 | Prefix buckets                    | **Only if needed**       | Only if chunking (3) is not enough.                                                                                                                                          |
|  6 | Cheaper `Pattern::matches()`      | **Only if profiled**     | Micro-optimizations; do them if a profile shows `matches()` dominating after 1 and 3.                                                                                        |
|  7 | Faster `Pattern::format()`        | **Drop for now**         | `generate_url()` is flat at 0.8 µs for 10 to 1,000 routes. There is nothing to win. `rawurlencode` is a correctness question, not performance.                               |
|  8 | Fewer allocations                 | **Drop for now**         | Not measured; the `static` counter in `Router` is only an issue if suggestion 4 happens.                                                                                     |
| 10 | Validate the table when built     | **API, not performance** | Detecting shadowed or duplicate routes is a usability gain (see SUGGESTIONS.md); also a prerequisite for safely reordering routes in 3.                                      |
| 11 | Atomic compiled file              | **Only with 4**          | Needed only if a compiled provider exists.                                                                                                                                   |
| 12 | Validate parameters in `format()` | **API, not performance** | Costs time. Only a usability feature; belongs in SUGGESTIONS.md.                                                                                                             |

## How requests are resolved today

`RequestResponderProvider::responder_for_request()` builds a `RouteProvider\ByUri` predicate and
hands it to a `RouteProvider`. With `Memoize` (`MemoizeByUri`), the work is:

1. Split routes once into _static_ (no params) and _dynamic_ routes. Dynamic routes are sorted by
   the number of `/` in their first static part.
2. Linear scan of the static routes, comparing `(string) $route->pattern === $path`.
3. Linear scan of the dynamic routes, running one `preg_match()` per route via `Pattern::matches()`,
   plus an `array_combine()` for the captured parameters.

Without `Memoize` (plain `Mutable` / `Immutable`), every lookup is a linear `array_find()` over all
routes calling the predicate, and `ByUri` does a `preg_match()` per dynamic route.

## How Symfony does it

`CompiledUrlMatcherDumper` compiles the route collection once (at cache-warm time) into a plain PHP
array that `CompiledUrlMatcher` consumes, so the request path does almost no work:

- **Static routes are a hash map** keyed by path: `$staticRoutes[$trimmedPathinfo]`. One array
  lookup, O (1), regardless of the number of static routes. The remaining entries under that key
  only differ by host, method, scheme, or condition.
- **Dynamic routes are grouped by static prefix** (`StaticPrefixCollection`), a prefix tree.
  Routes sharing `/blog/` are compiled together, so a request to `/users/12` never pays for the
  blog routes.
- **Each group is one big regex**, an alternation of all its routes, where every branch ends with
  `(*:N)`, a `MARK` identifying the matching route. One `preg_match()` finds the route _and_ the
  captured values; the `MARK` indexes into `$dynamicRoutes[N]` to get the route's data. PCRE JIT
  evaluates the alternation far faster than N separate `preg_match()` calls from PHP.
- **Regex size is bounded**: when the regex is too large, the dumper halves the chunk size and
  retries (`$chunkLimit`), yielding a list of regexes.
- **The result is dumped as a PHP file** (`return [...]`), loaded through OPcache. Nothing is parsed
  or compiled at runtime. The same goes for URL generation (`CompiledUrlGenerator`), which reads a
  precomputed array of `[variables, defaults, requirements, tokens, ...]` per route name.
- **Rewriting the path once**: `rawurldecode` and `rtrim` happen once per request, not per route.

## How FastRoute does it

FastRoute is much smaller than Symfony and reaches most of the same performance with fewer ideas:

- **Partitioned by HTTP method first.** Static routes are `$static[$method][$uri]`, dynamic routes
  `$dynamic[$method]`. A request only ever looks at its own method's tables, so method filtering
  disappears from the hot loop. Routes for any method are stored under `*` and tried last; `HEAD`
  falls back to `GET`.
- **Static routes are a hash map**, like Symfony: `isset($static[$method][$uri])`.
- **No prefix tree; flat chunking instead.** The dynamic routes of a method are split into chunks of
  about 30 routes (`MarkBased`; `GroupCountBased` uses 10). The chunk size is evened out
  (`ceil($count / round($count / 30))`), so 70 routes become 3 chunks of 24 rather than 30, 30, 10.
  Each chunk is one regex, `~^(?|route1(*MARK:a)|route2(*MARK:b)|...)$~`, matched with a single
  `preg_match()`. Chunking bounds regex size and keeps the cost of a miss proportional to
  `routes / 30` regex runs, not `routes`.
- **Alternatives to `MARK`.** `GroupCountBased` avoids `MARK` by padding each branch with empty
  groups `()` so that `count($matches)` identifies the route (it needs smaller chunks);
  `CharCountBased` and `GroupPosBased` are variations on the same theme. `MarkBased` is the default
  and the best choice on PCRE2.
- **Errors at registration, not at request time.** A duplicate route, or a static route shadowed by
  a
  variable route (`/users/me` after `/users/<id>`), throws `BadRouteException` while building.
- **Late `405`.** The allowed methods are only computed after every lookup has failed, by matching
  the
  URI against the other methods' tables. Hits never pay for it.
- **A tiny, robust file cache.** `FileCache` does `include $path` with a no-op error handler instead
  of `file_exists()` + `is_file()` + `is_readable()`, writes
  `'<?php return ' . var_export($data) . ';'`
  to a `.tmp` file under `LOCK_EX` and `rename()`s it, so concurrent requests never read a
  half-written
  file. The cached value is plain arrays, nothing to hydrate.
- **URI generation from preprocessed parts.** `FromProcessedConfiguration` keeps, per route name,
  the
  list of `string | [name, regex]` parts, and builds the path with a single loop. It validates each
  value against the parameter regex, which is a correctness feature that costs one `preg_match()`
  per parameter.

## Suggestions

The verdict for each one is in the triage table above; the entries below are the reasoning.

Ordered by expected return on effort.

### 1. Index static routes in a hash map

`MemoizeByUri::sort_routes()` already separates static routes, but then scans them with
`foreach`. Build `array<string, Route[]>` keyed by the pattern string (several routes can share a
path with different methods) and look up `$static[$path]`, then filter with `method_matches()`.

This is a small change, it removes an O (n) scan on the hottest path (static routes are typically
the most requested: `/`, `/login`, `/api/ping`), and mirrors Symfony's `$staticRoutes`.

Also cache `(string) $route->pattern`: it's a method call (`__toString`) per route per lookup today;
`$route->pattern->pattern` is a readonly property and cheaper.

### 2. Group dynamic routes by static prefix

The current weight, `substr_count($pattern->interleaved[0], '/')`, only _orders_ dynamic routes;
every route is still tried until one matches. Instead, bucket dynamic routes by the first path
segment of their static prefix (`/blog`, `/users`, ...), as `StaticPrefixCollection` does, and only
try the bucket matching the request's first segment, plus a fallback bucket for routes whose first
segment is dynamic (`/<lang:\w+>/...`).

FastRoute shows an even simpler option that needs no prefix at all: partition by method and split
into chunks of about 30 routes (see suggestion 3). Benchmark the first-segment buckets against plain
chunking before adopting the extra complexity. A flat first-segment map is a cheap 80% version of
Symfony's full prefix tree, and does not require a build step. It changes semantics if two routes
could both match and declaration order matters across buckets, so the fallback bucket must be merged
in declaration order, or the existing weight ordering must be preserved within it.

### 3. Combine dynamic routes into one regex with `MARK`

Replace N `preg_match()` calls with one per bucket:

```php
'#^(?|/blog/(\d{4})/([^/]+)(*:1)|/blog/tags/([^/]+)(*:2)|...)$#sD'
```

`(?|` is a branch-reset group, so captures from different alternatives reuse the same indexes. On
match, `$matches['MARK']` gives the route index and
`array_slice($matches, 1, count($route->pattern->params))`
the values. This is what Symfony does and what saves the most time on applications with dozens of
dynamic routes.

FastRoute and Symfony both bound the regex size: FastRoute with fixed chunks of about 30 routes
(evened out so the last chunk is not tiny), Symfony by halving until the regex compiles. Prefer
FastRoute's approach, as it is deterministic and needs no retry loop. Combine it with suggestion 2
if
the benchmark justifies it, otherwise chunking alone is enough.

Notes:

- `Pattern` already produces a regex per route (`Pattern::$regex`) with `#^...$#` delimiters; the
  combined regex needs the bare bodies, so `Pattern::parse()` should also expose the unanchored
  body.
- Method filtering (`method_matches()`) happens after the match; when the matched route rejects the
  method, resume with the next alternative (Symfony continues the `while (preg_match(...))` loop
  with the matched branch masked out).
- Keep the one-route-at-a-time fallback for a regex that fails to compile or exceeds PCRE limits,
  and
  chunk like `$chunkLimit` does.

### 4. Compile the route table to a PHP file

`Pattern`, `Route`, `Mutable` and `Immutable` all implement `__set_state()`, so they can already be
exported with `var_export()` and loaded through OPcache. Make that the documented, supported path
and push it further:

- Add a `RouteProvider\Compiled` (and a dumper) that exports the precomputed structures from
  suggestions 1 to 3 (static map, buckets, combined regexes, route data) as plain arrays, rather
  than object graphs. Arrays in OPcache are immutable shared memory (no per-request allocation);
  hydrating objects through `__set_state()` allocates every `Pattern` and `Route` on every request.
- Compile lazily: store only plain data, and instantiate the `Route` of the matched entry.
- Document a cache-warming recipe in `docs/RouteProviders.md`.

This is the structural difference with Symfony: the work is done at build time, not in
`sort_routes()` on the first request of each PHP process.

### 5. Avoid parsing the URI twice, and twice on failure

- `RequestResponderProvider` creates a `ByUri` (a `parse_url()` + `parse_str()`) and, on a miss,
  creates another one just to detect `MethodNotAllowed`. Let `ByUri` expose a cheap copy
  (`with_method(RequestMethod::METHOD_ANY)`, or a clone of the parsed state), or carry a flag so
  one lookup returns the matching route and whether any route matched with another method.
  Symfony gets this for free: `$allow` accumulates the methods of routes that matched the path.
  That also lets `MethodNotAllowed` report the `Allow` header's methods, which is useful in its own
  right.
- Parse `query_params` lazily: it is only needed after a match, and only if a query string exists.

### 6. Make `Pattern::matches()` cheaper

- `array_shift($matches)` followed by `array_combine()` copies the arrays twice. Use
  `array_slice($matches, 1)` or iterate with the index of `params`. With named groups
  `(?P<year>\d{4})` PCRE returns the keyed array directly (the integer keys still need removing,
  so benchmark it).
- Add the `D` (dollar-end-only) modifier so `$` does not match before a trailing newline (it is
  also slightly cheaper). PCRE JIT is the main accelerator for regex matching; mention
  `pcre.jit=1` in the docs.
- Check the cheap literal prefix before running the regex: store `static_prefix` on `Pattern`
  (the first element of `interleaved`) and skip with `str_starts_with($pathname, $prefix)`. Symfony
  stores the same value as `CompiledRoute::getStaticPrefix()`.
- A single trailing catch-all (`*`) route can skip the regex: `str_starts_with` and `substr`.

### 7. Speed up URL generation

`UrlGeneratorWithRouteProvider` builds a `ByIdOrAction` predicate, which `MemoizeByIdOrAction`
resolves from a map, so lookup is already O (1) with `Memoize`. The remaining costs are in
`Pattern::format_parts()`:

- It creates a closure (`$method`) on every call. Branch once on `is_array()` and use two loops
  instead.
- It walks `interleaved` and tests `$i % 2` for every part. Precompute, at parse time, a flat list
  of `[literal, name, literal, name, ..., literal]` or, better, a `sprintf`-style template plus
  ordered parameter names, so formatting becomes
  `vsprintf($this->template, array_map(urlencode..., $ordered_values))`. This is what Symfony does
  with its precomputed `tokens` array in `CompiledUrlGenerator`, and it moves all the parsing to
  build time.
- Use `rawurlencode()` rather than `urlencode()` for path segments: it is both correct for paths
  (`%20` rather than `+`) and avoids a behavior difference worth documenting. Treat it as a
  behavior change and note it in the CHANGELOG.

### 8. Reduce per-request allocations

- `RequestResponderProvider` allocates an anonymous class instance per request, and the first
  access to the class declaration costs something. Consider a named `final readonly class`
  (`MatchedRouteResponder`) so the class is declared once at autoload time, and the class table
  stays stable.
- `Mutable::route_for_predicate()` calls
  `array_find($this->routes, fn($route) => $predicate($route))`,
  wrapping the predicate in a closure on every call. `array_find($this->routes, $predicate)` is
  equivalent for callables and skips one call level per route.
- `Router` uses `static` counters to name anonymous actions. This is deterministic as long as routes
  are registered in the same order, but it makes compiled output (suggestion 4) fragile. Consider
  accepting an explicit action or deriving it from the method and pattern, so a compiled table
  remains valid across processes.

### 9. Partition routes by HTTP method

`Route::$methods` is `RequestMethod|RequestMethod[]`, and `method_matches()` is evaluated for every
candidate route. Build the tables per method at compile time (`$static[$method][$path]`,
`$dynamic[$method][]`), expanding a route with several methods into several entries and storing
`METHOD_ANY` routes under a fallback key tried last. The hot loop then never filters, and the
tables shrink: a `POST`-only request does not walk `GET` routes.

Edge cases to keep: `HEAD` falling back to `GET` (check what `RequestMethod` and the HTTP package do
today), and declaration order between a method-specific route and an `ANY` route on the same path.
This is also what makes the lazy `405` in suggestion 5 cheap: allowed methods are found by matching
the other tables, only after a miss.

### 10. Validate the route table when it is built

FastRoute rejects, at registration, duplicate routes and static routes shadowed by an earlier
dynamic route. Both are silent today (the first match wins). A check in `Immutable` / the compiled
dumper costs nothing at request time and, once routes are compiled, lets the matcher assume
the table is consistent (no ordering subtleties, which is a precondition for reordering routes into
buckets and chunks safely). It is a behavior change, so it belongs in the CHANGELOG, probably
as an exception thrown by the compile step, not by `Mutable::add_routes()`.

### 11. Write the compiled file atomically

For suggestion 4, copy FastRoute's `FileCache`: write to `"$path.tmp"` with `LOCK_EX`, `chmod`, then
`rename()`, so a concurrent request never includes a truncated file. Load it with a plain `include`
and a silenced error handler instead of `file_exists()` + `is_file()` + `is_readable()`; with
OPcache the `include` is the only filesystem touch. Reuse one static no-op `Closure` for the error
handler instead of allocating one per call.

### 12. Optionally validate parameters when generating URLs

`Pattern::format()` does no validation: `format([ 'id' => 'abc' ])` for `<id:\d+>` produces a URL
that will never match. FastRoute validates each value with its parameter regex (`GenerateUri`),
which costs a `preg_match()` per parameter. If added, make it opt-in or reserve it for debug
mode, and keep the unvalidated path as the default for speed. It pairs well with the clear
missing-value
error proposed in [SUGGESTIONS.md](SUGGESTIONS.md).

## Measuring

Do this before and after any change, otherwise the suggestions above are guesses.

- FastRoute ships `phpbench` benchmarks (`RoutingWithManyRoutesBench`,
  `RoutingWithRealLifeExampleBench`,
  `UriGenerationBench`, `RouteRegistrationBench`) worth borrowing scenarios from, notably a
  real-life
  route table, and registration time, which matters for cold starts.
- `benchmarks/run.php` (no extra dependency) builds tables of 10, 100 and 1,000 routes (half static,
  half dynamic, five prefixes) and times `Immutable` and `Memoize(Immutable)` on: first/last static
  route, first/last dynamic route, no match, cold start (build + one lookup), and `generate_url()`.
  Run it with `make benchmark`, or
  `php -d opcache.enable_cli=1 benchmarks/run.php --iterations=20000`.
  `--sizes=10,100` and `--json` are available to focus or to diff two runs.
- Cold numbers matter for PHP-FPM, where each request rebuilds the maps; warm numbers matter for
  long-running runtimes (RoadRunner, Swoole, FrankenPHP).
- Record `memory_get_peak_usage()` too: compiled arrays in OPcache should lower per-request memory
  (the script prints it in its header).

### Baseline

PHP 8.5.11, OPcache on, JIT PCRE on, 5,000 iterations, µs per call, `Memoize(Immutable)`:

| routes | static, last | dynamic, last | no match | cold | generate_url |
|-------:|-------------:|--------------:|---------:|-----:|-------------:|
|     10 |         1.09 |          2.02 |     1.95 |  3.7 |         0.80 |
|    100 |         4.85 |         12.10 |    11.91 | 27.8 |         0.80 |
|  1,000 |        52.54 |        123.64 |   117.84 |  883 |         0.79 |

Matching is linear in the number of routes (about 10x per 10x), for static routes too, which is what
suggestions 1 to 3 address. `generate_url()` is flat: the map lookup is not the problem, the
formatting is (suggestion 7). Plain `Immutable` is slower still (347 µs for the last dynamic route
at 1,000), so `Memoize` is already worth it. Numbers vary by machine; compare runs on the same one.

### After PERF01 (static hash map)

`Memoize(Immutable)`, same settings, µs per call:

| routes | static, last | dynamic, last | no match | cold |
|-------:|-------------:|--------------:|---------:|-----:|
|     10 | 0.53 (was 1.09) | 1.37 (was 2.02) | 1.24 (was 1.95) | 4.2 (was 3.7) |
|    100 | 0.53 (was 4.85) | 7.22 (was 12.10) | 7.03 (was 11.91) | 24 (was 28) |
|  1,000 | 0.55 (was 52.5) | 82.7 (was 123.6) | 78.3 (was 117.8) | 305 (was 883) |

Static matching is now flat. Dynamic and no-match lookups also got cheaper because they no longer
scan the static routes first; they are still linear (suggestion 3).

### After PERF03 (chunked combined regex)

`Memoize(Immutable)`, µs per call, with chunks of about 30 routes (measured since PERF01):

| routes | dynamic, last | no match |
|-------:|--------------:|---------:|
|     10 | 0.84 (was 1.37) | 0.56 (was 1.24) |
|    100 | 0.98 (was 7.22) | 0.69 (was 7.03) |
|  1,000 | 5.30 (was 82.7) | 4.21 (was 78.3) |

Chunk size was tried at 10, 30 and 100 routes: at 1,000 routes the last dynamic route takes 7.6, 5.1
and 3.5 µs respectively, and cold start is the same for all (PCRE compiling the regexes dominates).
30 was kept as a moderate value; if the regex of a chunk fails to run (e.g. JIT stack limit) the
routes of the chunk are tried one by one instead of silently missing.

Cold start with dynamic routes is dominated by regex compilation (about 2.5 ms for 500 dynamic
routes, vs 3.4 ms before), which only a compiled/cached route table (suggestion 4) can avoid.
The cold benchmark now uses unique patterns per iteration so PCRE's cache doesn't hide this.

### Where a PHP-FPM request spends its time (after PERF03)

Lookup is now 0.5 to 5 µs, but under PHP-FPM nothing survives between requests, so each request
first loads the route table and rebuilds `Memoize`'s indexes. `benchmarks/run.php` measures it as
`request: include + … match`: the table exported with `var_export()` to a file loaded through
OPcache (`__set_state()` hydration), wrapped in `Memoize`, then one lookup:

| routes | request (µs) | of which lookup |
|-------:|-------------:|----------------:|
|     10 |          6.6 |           ~0.5  |
|    100 |         59.8 |           ~0.5  |
|  1,000 |          654 |       ~0.5 to 5 |

The setup is split about evenly between hydrating the `Route` and `Pattern` objects (`__set_state()`,
roughly 37 µs per 100 routes) and `Memoize` building its static map and chunk regexes (roughly 28 µs
per 100 routes). Both are repeated on every request and are two orders of magnitude above the
lookup. This is what suggestion 4 targets: a compiled table holding only constant arrays
(static map, chunk regexes, per-route data), which OPcache shares across requests without
allocating anything, and which instantiates only the matched `Route`.

### After PERF04 (compiled provider)

`RouteProvider\Compiled` loaded from an exported file, same scenarios, µs per request:

| routes | include + Memoize, static | Compiled, static | include + Memoize, dynamic | Compiled, dynamic |
|-------:|--------------------------:|-----------------:|---------------------------:|------------------:|
|     10 |                       7.1 |              1.0 |                        9.7 |               1.5 |
|    100 |                      70.7 |              1.0 |                       72.4 |               3.4 |
|  1,000 |                     777   |              2.9 |                      766   |               6.0 |

Lookups by id or action are indexed in the compiled data and create a single `Route`
(0.8 µs at 100 and 1,000 routes), so URL generation does not hydrate the table either.

Note: OPcache does not cache files younger than `opcache.file_update_protection` (2 s); the
benchmark sets it to 0, otherwise it measures compilation.

A good target to claim a success: matching a dynamic route among 1,000 routes should cost within a
small constant of matching one among 10.

## Suggested order

1. Benchmarks (the *Measuring* section).
2. Static hash map (suggestion 1) and the cheap fixes (5, 6, 8). Low risk, no API change.
3. Method partitioning (9), then chunked combined regex (3). Behind `Memoize`, no API change.
   Try first-segment bucketing (2) only if the benchmark shows chunking is not enough.
4. Compiled provider (4, 11), build-time validation (10) and the faster `Pattern::format()` (7, 12),
   which need new public classes and documentation, and an entry in [CHANGELOG.md](CHANGELOG.md).
