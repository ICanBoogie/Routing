# Routing

[![Packagist](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Code Coverage](https://coveralls.io/repos/github/ICanBoogie/Routing/badge.svg?branch=7.0)](https://coveralls.io/r/ICanBoogie/Routing?branch=7.0)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A Request is mapped to a
Route, which in turn is mapped to a Responder. If the process is successful a response is returned.
Events are emitted along the way to allow listeners to alter the request or the response, or recover
from failure.

Routes are defined with attributes on plain controller classes. Path, query string, and body values
are cast to the types of the action's parameters, and routes work both ways: they match requests and
generate URLs.

```php
<?php

namespace App\Articles;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Prefix;
use ICanBoogie\Routing\Attribute\Query;
use ICanBoogie\Routing\IntoResponse\Json;

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}

#[Prefix(pattern: '/articles')]
final readonly class ArticlesController
{
    public function __construct(
        private ArticleRepository $articles,
    ) {
    }

    // GET /articles?page=2
    #[Get(id: 'articles')]
    public function list(#[Query] int $page = 1): Json
    {
        return new Json($this->articles->page($page));
    }

    // GET /articles/42 — "/articles/abc" doesn't match: 404
    #[Get('/<id:\d+>', id: 'article')]
    public function show(int $id): Json
    {
        return new Json($this->articles->get($id));
    }

    // GET /articles/status/draft — "/articles/status/foo" is a 404
    #[Get('/status/:status')]
    public function by_status(Status $status): Json
    {
        return new Json($this->articles->with_status($status));
    }

    // POST /articles, with a form or a JSON body — a missing "title" is a 400
    #[Post]
    public function create(#[Part] string $title, #[Part] Status $status = Status::DRAFT): Json
    {
        return new Json($this->articles->create($title, $status), status: 201);
    }
}
```

Build the configuration, answer requests, and generate URLs:

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder\DelegateToProvider;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\CacheStrategy;
use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\RequestResponderProvider;

/* @var \Psr\Container\ContainerInterface $container */
/* @var \ICanBoogie\Routing\UrlGenerator $url_generator */

$config = new ConfigBuilder()
    ->add_controller(App\Articles\ArticlesController::class)
    ->cache_strategy(CacheStrategy::COMPILE) // compiled routes for production, exportable with var_export()
    ->build();

$responders = new FromConfig($config, fn(string $class): object => $container->get($class));
$application = new DelegateToProvider(new RequestResponderProvider($config->routes, $responders));

$response = $application->respond(Request::from($_SERVER));
$response();

$url_generator->generate_url('article', [ 'id' => 42 ]);  // "/articles/42"
$url_generator->generate_url('articles');                 // "/articles"
$url_generator->generate_url('articles:by_status', [ 'status' => 'draft' ]); // "/articles/status/draft"
```

Other features include [middleware and events](docs/Middleware.md) to run code around actions,
[custom decoders](docs/StatelessControllers.md#decoders) to turn values into objects, and
[route providers](docs/RouteProviders.md) that make route lookup fast.



#### Installation

```shell
composer require icanboogie/routing
```



## Documentation

New to the package? Follow [Getting started](docs/GettingStarted.md). The full index is in [docs/](docs/README.md).

- **Routes**: [Route patterns](docs/RoutePatterns.md) · [Defining routes](docs/CreatingRoutes.md) ·
  [Route providers](docs/RouteProviders.md) · [URL generation](docs/UrlGeneration.md)
- **Handling requests**: [Using the Router](docs/Router.md) ·
  [Responding to a request](docs/RespondingToRequests.md) ·
  [Action responders](docs/ActionResponders.md) · [Middleware and events](docs/Middleware.md)
- **Controllers**: [Stateless controllers](docs/StatelessControllers.md) ·
  [Controllers](docs/Controllers.md) · [Action controllers](docs/ActionControllers.md)
- **Reference**: [Exceptions](docs/Exceptions.md)



----------



## Continuous Integration

The project is continuously tested by [GitHub Actions](https://github.com/ICanBoogie/Routing/actions).

[![Tests](https://github.com/ICanBoogie/Routing/actions/workflows/test.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Routing/actions/workflows/test.yml)
[![Static Analysis](https://github.com/ICanBoogie/Routing/actions/workflows/static-analysis.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Routing/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/ICanBoogie/Routing/actions/workflows/code-style.yml/badge.svg?branch=7.0)](https://github.com/ICanBoogie/Routing/actions/workflows/code-style.yml)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you're expected to uphold this code.



## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.
