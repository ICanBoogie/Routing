<?php

/**
 * Micro-benchmark for route resolution and URL generation.
 *
 *     php benchmarks/run.php [--iterations=N] [--sizes=10,100,1000] [--json]
 *
 * Run with `-d opcache.enable_cli=1 -d pcre.jit=1` to approximate production.
 */

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\RouteProvider\ByIdOrAction;
use ICanBoogie\Routing\RouteProvider\ByUri;
use ICanBoogie\Routing\RouteProvider\Immutable;
use ICanBoogie\Routing\RouteProvider\Memoize;
use ICanBoogie\Routing\UrlGenerator\UrlGeneratorWithRouteProvider;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [ 'iterations::', 'sizes::', 'json' ]);
$iterations = (int)($options['iterations'] ?? 20_000);
$sizes = array_map('intval', explode(',', $options['sizes'] ?? '10,100,1000'));
$json = isset($options['json']);

/**
 * Builds a table of $size routes, half static and half dynamic, spread over a few prefixes.
 *
 * @return Route[]
 */
function make_routes(int $size): array
{
    $routes = [];
    $prefixes = [ 'blog', 'users', 'shop', 'admin', 'api' ];

    for ($i = 0; $i < $size; $i++) {
        $prefix = $prefixes[$i % count($prefixes)];
        $pattern = $i % 2
            ? "/$prefix/section-$i/<id:\d+>/:slug"
            : "/$prefix/page-$i";

        $routes[] = new Route(
            $pattern,
            "action:$i",
            $i % 3 ? RequestMethod::METHOD_GET : RequestMethod::METHOD_ANY,
            "route_$i",
        );
    }

    return $routes;
}

/**
 * @return float Nanoseconds per call.
 */
function measure(callable $fn, int $iterations): float
{
    for ($i = 0; $i < min(500, $iterations); $i++) { // warm up
        $fn();
    }

    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    return (hrtime(true) - $start) / $iterations;
}

$results = [];

foreach ($sizes as $size) {
    $routes = make_routes($size);

    // Last static is the last even index, last dynamic the last odd index.
    $last_static = $size - 1 - (($size - 1) % 2);
    $last_dynamic = $size - 1 - (($size - 1) % 2 === 0 ? 1 : 0);
    $prefixes = [ 'blog', 'users', 'shop', 'admin', 'api' ];
    $p = fn(int $i): string => $prefixes[$i % 5];

    $uris = [
        'static, first' => "/{$p(0)}/page-0",
        'static, last' => "/{$p($last_static)}/page-$last_static",
        'dynamic, first' => $size > 1 ? "/{$p(1)}/section-1/12/hello-world" : null,
        'dynamic, last' => $size > 1 ? "/{$p($last_dynamic)}/section-$last_dynamic/12/hello-world" : null,
        'no match' => '/nothing/here/at/all',
    ];

    $cold = [];
    $providers = [
        'Immutable' => fn(): RouteProvider => new Immutable($routes),
        'Memoize(Immutable)' => fn(): RouteProvider => new Memoize(new Immutable($routes)),
    ];

    foreach ($providers as $name => $factory) {
        // Cold: build the provider and resolve one URI, as the first request of a process would.
        $cold_ns = measure(function () use ($factory, $uris) {
            $factory()->route_for_predicate(new ByUri($uris['static, last']));
        }, max(20, intdiv($iterations, 200)));

        $results[] = [ 'size' => $size, 'provider' => $name, 'scenario' => 'cold (build + 1 lookup)', 'ns' => $cold_ns ];

        $provider = $factory();

        foreach ($uris as $scenario => $uri) {
            if ($uri === null) {
                continue;
            }

            // Plain providers are linear; scale iterations down so large tables stay quick.
            $n = $name === 'Immutable' ? max(100, intdiv($iterations, max(1, intdiv($size, 10)))) : $iterations;

            $ns = measure(fn() => $provider->route_for_predicate(new ByUri($uri, RequestMethod::METHOD_GET)), $n);
            $results[] = [ 'size' => $size, 'provider' => $name, 'scenario' => "match: $scenario", 'ns' => $ns ];
        }

        $generator = new UrlGeneratorWithRouteProvider($provider);
        $ns = measure(fn() => $generator->generate_url("route_" . ($size > 1 ? 1 : 0), [ 'id' => 12, 'slug' => 'hello world' ]), $iterations);
        $results[] = [ 'size' => $size, 'provider' => $name, 'scenario' => 'generate_url (dynamic)', 'ns' => $ns ];
    }
}

if ($json) {
    echo json_encode($results, JSON_PRETTY_PRINT), "\n";
    exit(0);
}

printf(
    "PHP %s, opcache=%s, pcre.jit=%s, iterations=%d, peak memory=%.1f MiB\n\n",
    PHP_VERSION,
    ini_get('opcache.enable_cli') ?: '0',
    ini_get('pcre.jit'),
    $iterations,
    memory_get_peak_usage() / 1048576,
);
printf("%6s  %-20s  %-26s  %12s\n", 'routes', 'provider', 'scenario', 'µs / call');
echo str_repeat('-', 70), "\n";

foreach ($results as $r) {
    printf("%6d  %-20s  %-26s  %12.2f\n", $r['size'], $r['provider'], $r['scenario'], $r['ns'] / 1000);
}
