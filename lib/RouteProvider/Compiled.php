<?php

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

use function array_find;
use function array_keys;
use function array_map;
use function is_array;
use function var_export;

/**
 * A route provider backed by plain data, designed to be exported to a PHP file and loaded through OPcache.
 *
 * Decorating a provider with {@see Memoize} speeds up the search for routes, but the work is lost when the
 * process ends, which is every request with PHP-FPM. With a compiled provider the indexes are computed once,
 * and the data is made of constant arrays that OPcache shares between requests. Routes are created on demand,
 * only the ones that are actually needed.
 *
 * ```php
 * // At build time, or during deployment
 * $tmp = "$file." . uniqid('', true);
 * file_put_contents($tmp, Compiled::dump($routes));
 * rename($tmp, $file); // atomic, a concurrent request never reads a partial file
 *
 * // At runtime
 * $routes = new Compiled(require $file);
 * ```
 *
 * The built-in predicates {@see ById}, {@see ByAction}, {@see ByIdOrAction}, and {@see ByUri} are optimized.
 * Other predicates are tested against every route.
 *
 * @phpstan-type RouteData array{
 *     pattern: array{ pattern: string, interleaved: array<string|string[]>, params: array<int, int|string>, regex: string },
 *     action: string,
 *     methods: string|string[],
 *     id: string|null,
 *     extensions: object[],
 * }
 * @phpstan-type CompiledData array{
 *     routes: RouteData[],
 *     by_id: array<string, int>,
 *     by_action: array<string, int>,
 *     by_uri: array{ static: array<string, int[]>, chunks: array<array{ regex: string, routes: int[] }> },
 * }
 */
final class Compiled implements RouteProvider
{
    /**
     * Compiles routes into plain data.
     *
     * @param iterable<Route> $routes
     *
     * @return CompiledData
     */
    public static function compile(iterable $routes): array
    {
        $routes = [ ...$routes ];
        $data = [];
        $by_id = [];
        $by_action = [];

        foreach ($routes as $i => $route) {
            $pattern = $route->pattern;
            $methods = $route->methods;

            $data[] = [
                'pattern' => [
                    'pattern' => $pattern->pattern,
                    'interleaved' => $pattern->interleaved,
                    'params' => $pattern->params,
                    'regex' => $pattern->regex,
                ],
                'action' => $route->action,
                'methods' => is_array($methods) ? array_map(fn(RequestMethod $m) => $m->value, $methods) : $methods->value,
                'id' => $route->id,
                'extensions' => $route->extensions,
            ];

            // Same semantics as MemoizeById and MemoizeByAction, the last route wins.
            if ($route->id) {
                $by_id[$route->id] = $i;
            }

            $by_action[$route->action] = $i;
        }

        return [
            'routes' => $data,
            'by_id' => $by_id,
            'by_action' => $by_action,
            'by_uri' => ByUriIndex::compile($routes),
        ];
    }

    /**
     * Compiles routes and returns the PHP code of a file that returns the compiled data.
     *
     * **Note**: Route extensions must be exportable with `var_export()`, that is to say, they
     * must implement `__set_state()`.
     *
     * @param iterable<Route> $routes
     */
    public static function dump(iterable $routes): string
    {
        return '<?php return ' . var_export(self::compile($routes), true) . ';' . "\n";
    }

    /**
     * @var Route[]
     *     Routes created so far, where _key_ is the position of the route.
     */
    private array $instances = [];

    private readonly ByUriIndex $index;

    /**
     * @param CompiledData $data
     *     The data returned by {@see compile()}.
     */
    public function __construct(
        private readonly array $data,
    ) {
        $this->index = new ByUriIndex($data['by_uri'], $this->route_at(...));
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByUri) {
            return $this->index->find($predicate);
        }

        if ($predicate instanceof ById) {
            return $this->route_at_or_null($this->data['by_id'][$predicate->id] ?? null);
        }

        if ($predicate instanceof ByAction) {
            return $this->route_at_or_null($this->data['by_action'][$predicate->action] ?? null);
        }

        if ($predicate instanceof ByIdOrAction) {
            return $this->route_at_or_null(
                $this->data['by_id'][$predicate->id_or_action]
                ?? $this->data['by_action'][$predicate->id_or_action]
                ?? null
            );
        }

        return array_find([ ...$this ], $predicate);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_map($this->route_at(...), array_keys($this->data['routes'])));
    }

    private function route_at_or_null(int|null $position): ?Route
    {
        return $position === null ? null : $this->route_at($position);
    }

    private function route_at(int $position): Route
    {
        return $this->instances[$position] ??= $this->make_route($this->data['routes'][$position]);
    }

    /**
     * @param RouteData $data
     */
    private function make_route(array $data): Route
    {
        $methods = $data['methods'];

        return new Route(
            Pattern::__set_state($data['pattern']),
            $data['action'],
            is_array($methods) ? array_map(RequestMethod::from(...), $methods) : RequestMethod::from($methods),
            $data['id'],
            $data['extensions'],
        );
    }
}
