- Keep the documentation consistent with changes to the codebase.
- Keep CHANGELOG.md up to date with changes.
- Improve the documentation when it can improve the user experience.
- Performance work follows [PERFORMANCE.md](PERFORMANCE.md):
  - Prefix the commit message with `PERF<nn>:`, where `<nn>` is the number of the suggestion in
    PERFORMANCE.md (e.g. `PERF01: Index static routes by path in MemoizeByUri`). Changes that are
    not tied to a numbered suggestion use `PERF:`.
  - A `PERF` change must be justified by a measured improvement (`make benchmark`) or by a better
    API. Do not adopt something only because Symfony or FastRoute does it; drop it when the
    benchmark does not show a win.
  - One optimization per commit, including its tests and documentation, so it can be reverted
    cleanly and compared by benchmarking the commit before it.
  - Put the before/after numbers from `make benchmark` in the commit body.
- `RouteProvider\Compiled` copies the state of `Pattern` and `Route` into plain arrays and rebuilds the
  objects from them. When the properties or constructor parameters of `Pattern` or `Route` change,
  update `Compiled::__construct()` and `Compiled::make_route()` (and `Pattern::__set_state()` if needed)
  to keep them in sync, otherwise compiled providers silently return different routes.
