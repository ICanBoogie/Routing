# Routing

[![Packagist](https://img.shields.io/packagist/v/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)
[![Code Coverage](https://coveralls.io/repos/github/ICanBoogie/Routing/badge.svg?branch=7.0)](https://coveralls.io/r/ICanBoogie/Routing?branch=7.0)
[![Downloads](https://img.shields.io/packagist/dt/icanboogie/routing.svg)](https://packagist.org/packages/icanboogie/routing)

The **icanboogie/routing** package handles URL rewriting in native PHP. A Request is mapped to a
Route, which in turn is mapped to a Responder. If the process is successful a response is returned.
Events are emitted along the way to allow listeners to alter the request or the response, or recover
from failure.

The following example is an overview of request processing. The routing components are part of the
stack of responder providers.

```php
<?php

namespace ICanBoogie\HTTP;

/* @var ResponderProvider $responder_provider */

// The request is usually created from the $_SERVER super global.
$request = Request::from($_SERVER);

// The Responder Provider matches a request with a Responder
$responder = $responder_provider->responder_for_request($request);

// The Responder responds to the request with a Response, it might also throw an exception.
$response = $responder->respond($request);

// The response is sent to the client.
$response();
```



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
- **Controllers**: [Controllers](docs/Controllers.md) · [Action controllers](docs/ActionControllers.md)
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
