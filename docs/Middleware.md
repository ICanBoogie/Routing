# Middleware and events

Middleware wraps a responder to run code before and/or after it.

```php
interface Middleware
{
    public function responder(Responder $next): Responder;
}
```

Middleware is applied with [ActionResponderProvider\WithMiddleware][] and a
[MiddlewareCollection][]; see [Action responders](ActionResponders.md#combining-providers). The
first middleware added wraps the endpoint, each following one wraps the previous.

## The `Alter` middleware

`Middleware\Alter` turns the middleware into events, which is the easiest way for third parties
(modules, plugins) to hook into routing without touching your wiring.

- [Route\BeforeRespondEvent][] is emitted before the responder is invoked. Set the event's
  `response` to answer the request and skip the responder — useful for redirections or cached
  responses.
- [Route\RespondEvent][] is emitted after the response is created. Listeners may replace
  `response`.

Both events are sent by the matched `Route`, so listeners can be attached to a specific route as
well as to the event class. The `request` is available on the event.

```php
<?php

use ICanBoogie\Routing\Route;

use function ICanBoogie\get_events;

get_events()->attach(function (Route\BeforeRespondEvent $event, Route $route) {
    if ($route->action === 'articles:show' && ($cached = fetch_from_cache($event->request))) {
        $event->response = $cached;
    }
});
```

Controllers have their own, finer events, see [Controllers](Controllers.md).

## Writing middleware

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;

final class AddHeader implements Middleware
{
    public function responder(Responder $next): Responder
    {
        return new class ($next) implements Responder {
            public function __construct(private Responder $next)
            {
            }

            public function respond(Request $request): Response
            {
                $response = $this->next->respond($request);
                $response->headers['X-Routed'] = 'yes';

                return $response;
            }
        };
    }
}
```

## The request context

`RequestResponderProvider` matches the route, then wraps the responder it got from the
`ActionResponderProvider`. When the wrapper responds, it:

1. adds the matched `Route` to `$request->context`;
2. merges the values captured from the path into `$request->path_params`;
3. calls the wrapped responder.

```php
<?php

use ICanBoogie\Routing\Route;

$route = $request->context->get(Route::class);
```

Everything the `ActionResponderProvider` returns runs _after_ the route was added: middleware
applied with `WithMiddleware`, `Alter`, and the endpoint responder. Code that wraps the application
itself (e.g. a decorator around `DelegateToProvider`, or around the `RequestResponderProvider`) runs
_before_, the route is not in the context yet, and `get(Route::class)` fails. Use middleware when you
need the route.

## Use case: requiring authentication

Throw `ICanBoogie\HTTP\AuthenticationRequired` from a middleware when the request is not
authenticated. The exception is a `ClientError` (401); as with any exception raised while
responding, it is up to the HTTP layer to turn it into a response, e.g. a login form or a redirection.

Not every route needs authentication, so mark the ones that do. Route extensions are objects attached
to a route; they must implement `__set_state()` to survive `RouteProvider\Compiled`.

```php
<?php

final readonly class RequiresAuthentication
{
    public static function __set_state(array $an_array): self
    {
        return new self();
    }
}
```

```php
<?php

use ICanBoogie\HTTP\AuthenticationRequired;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;
use ICanBoogie\Routing\Route;

final class RequireAuthentication implements Middleware
{
    public function responder(Responder $next): Responder
    {
        return new readonly class ($next) implements Responder {
            public function __construct(private Responder $next)
            {
            }

            public function respond(Request $request): Response
            {
                $route = $request->context->get(Route::class);

                if ($this->requires_authentication($route) && !$this->is_authenticated($request)) {
                    throw new AuthenticationRequired();
                }

                return $this->next->respond($request);
            }

            private function requires_authentication(Route $route): bool
            {
                foreach ($route->extensions as $extension) {
                    if ($extension instanceof RequiresAuthentication) {
                        return true;
                    }
                }

                return false;
            }

            private function is_authenticated(Request $request): bool
            {
                // `$request->cookie` is the `$_COOKIE` super global, it is not validated.
                // Verify the token with your own session or token service.
                return isset($request->cookie['auth']);
            }
        };
    }
}
```

Declare the route with the extension, and add the middleware to the collection. The first middleware
added is the closest to the endpoint, so add `RequireAuthentication` before `Alter` to have
`BeforeRespondEvent` listeners run first, or after it to have the check run first.

```php
<?php

use ICanBoogie\Routing\Route;

new Route('/account', 'account:show', extensions: [ new RequiresAuthentication() ]);

new MiddlewareCollection([ new Alter(), new RequireAuthentication() ]);
```

> [!TIP]
> If the protected routes share an action prefix, such as `admin:users:index`, you can skip the
> extension and test `$route->action` instead, e.g. `str_starts_with($route->action, 'admin:')`.
> Nothing to mark on each route, but the convention then carries the security rule.



[ActionResponderProvider\WithMiddleware]: ../lib/ActionResponderProvider/WithMiddleware.php
[MiddlewareCollection]: ../lib/MiddlewareCollection.php
[Route\BeforeRespondEvent]: ../lib/Route/BeforeRespondEvent.php
[Route\RespondEvent]: ../lib/Route/RespondEvent.php
