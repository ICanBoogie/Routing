# Controllers

Previous examples demonstrated how closures could be used to handle routes. Closures are
perfectly fine when you start building your application, but as soon as it grows, you might want
to use controller classes instead to better organize your application. You can map each route to
its [ControllerAbstract][] class, or use the [ActionTrait][] to group related HTTP
request handling logic into a single controller.

## Controller response

A controller implements `Responder`. Its `respond()` method is final and invokes the `action()`
method, which may return a [Response][], a `Closure`, a `Stringable`, a string, or `null`.

A built-in response is available through `$this->response`. If the result is a [Response][]
instance, it is returned as is; if the result is anything else but `null`, it is used as the body
of the built-in response, which is returned; if the result is `null`, the built-in response is
returned unaltered.

## Before the action is executed

[Controller\BeforeActionEvent][] is emitted before the `action()` method is invoked. Listeners may
provide a result through the event's `result` property and thus cancel the action. Event hooks may
also use this event to alter the controller before the action is executed.

## After the action is executed

[Controller\ActionEvent][] is emitted after the `action()` method was invoked. Listeners may alter
the result through the event's `result` property.

## Basic controllers

Basic controllers extend from [ControllerAbstract][] and must implement the `action()` method.

> [!NOTE]
> The `action()` method is invoked _from within_ the controller, by the final `respond()` method,
> and is better defined as _protected_.

```php
<?php

namespace App\Modules\Articles\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\ControllerAbstract;

final class DeleteController extends ControllerAbstract
{
    protected function action(Request $request): Response|string|null
    {
        // Your code goes here and should return a string or a Response instance
    }
}
```

The following properties are provided by the [ControllerAbstract][] class:

- `request`: The request being dispatched.
- `route`: The route being dispatched.
- `response`: The built-in response (protected).

> [!NOTE]
> A controller has a state and can't process multiple requests. Use a fresh instance for each
> request.

## Action controllers

See [Action controllers](ActionControllers.md).



[Response]:                            https://github.com/ICanBoogie/HTTP/blob/7.0/lib/Response.php
[ActionTrait]:                         ../lib/Controller/ActionTrait.php
[ControllerAbstract]:                  ../lib/ControllerAbstract.php
[Controller\BeforeActionEvent]:        ../lib/Controller/BeforeActionEvent.php
[Controller\ActionEvent]:              ../lib/Controller/ActionEvent.php
