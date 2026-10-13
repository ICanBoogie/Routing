<?php

/**
 * Micro-benchmark for the dispatch of an action: from a request whose route is known to the response.
 *
 *     php benchmarks/dispatch.php [--iterations=N] [--json]
 *
 * It compares the controllers that extend `ControllerAbstract`, a new instance per request as they
 * require, with `MethodResponder`, which calls the method of a shared stateless controller. Route
 * matching is not part of it, see `run.php`.
 *
 * Run with `-d opcache.enable_cli=1 -d pcre.jit=1` to approximate production.
 */

namespace Benchmark;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\Controller\ActionTrait;
use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Route;

require __DIR__ . '/../vendor/autoload.php';

final class BasicController extends ControllerAbstract
{
    protected function action(Request $request): Response
    {
        return new Response('article ' . $request->path_params['id']);
    }
}

final class ActionController extends ControllerAbstract
{
    use ActionTrait;

    protected function any_articles_show(string $id): string
    {
        return "article $id";
    }
}

final class ArticleController
{
    #[Get('/articles/:id')]
    public function show(int $id): Response
    {
        return new Response("article $id");
    }
}

function make_request(): Request
{
    $request = Request::from([ 'uri' => '/articles/12', 'method' => RequestMethod::METHOD_GET ]);
    $request->context->add(new Route('/articles/:id', 'articles:show'));
    $request->path_params = [ 'id' => '12' ];

    return $request;
}

/**
 * @return float Nanoseconds per call.
 */
function measure(callable $fn, int $iterations): float
{
    for ($i = 0; $i < min(1000, $iterations); $i++) { // warm up
        $fn();
    }

    $start = hrtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    return (hrtime(true) - $start) / $iterations;
}

$options = getopt('', [ 'iterations::', 'json' ]);
$iterations = (int)($options['iterations'] ?? 200_000);
$json = isset($options['json']);

$config = new ConfigBuilder()->add_controller(ArticleController::class)->build();
$controller = new ArticleController();
$provider = new FromConfig($config, fn() => $controller);
$responder = $provider->responder_for_action('articles:show');

$request = make_request();

$scenarios = [
    'ControllerAbstract (new instance)' => fn() => new BasicController()->respond($request),
    'ControllerAbstract + ActionTrait (new instance)' => fn() => new ActionController()->respond($request),
    'MethodResponder (shared controller)' => fn() => $responder->respond($request),
    'FromConfig lookup + MethodResponder' => fn() => $provider->responder_for_action('articles:show')
        ->respond($request),
];

$results = [];

foreach ($scenarios as $scenario => $fn) {
    $results[] = [ 'scenario' => $scenario, 'ns' => measure($fn, $iterations) ];
}

if ($json) {
    echo json_encode($results, JSON_PRETTY_PRINT), "\n";
    exit(0);
}

printf(
    "PHP %s, opcache=%s, pcre.jit=%s, iterations=%d\n\n",
    PHP_VERSION,
    ini_get('opcache.enable_cli') ?: '0',
    ini_get('pcre.jit'),
    $iterations,
);
printf("%-50s  %12s\n", 'scenario', 'µs / call');
echo str_repeat('-', 64), "\n";

foreach ($results as $r) {
    printf("%-50s  %12.2f\n", $r['scenario'], $r['ns'] / 1000);
}
