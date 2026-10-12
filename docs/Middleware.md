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



[ActionResponderProvider\WithMiddleware]: ../lib/ActionResponderProvider/WithMiddleware.php
[MiddlewareCollection]: ../lib/MiddlewareCollection.php
[Route\BeforeRespondEvent]: ../lib/Route/BeforeRespondEvent.php
[Route\RespondEvent]: ../lib/Route/RespondEvent.php
