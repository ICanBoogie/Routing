# Action responders

A route only carries an _action_ (e.g. `articles:show`). An [ActionResponderProvider][] maps that
action to the `Responder` that handles it:

```php
public function responder_for_action(string $action): ?Responder;
```

Several implementations are provided, and they compose.

| Class                                 | Use it to…                                                    |
|---------------------------------------|---------------------------------------------------------------|
| `ActionResponderProvider\Mutable`     | Register responders at runtime with `add_responder()`        |
| `ActionResponderProvider\Immutable`   | Provide a fixed `action => Responder` array                   |
| `ActionResponderProvider\Container`   | Resolve responders from a PSR-11 container                    |
| `ActionResponderProvider\FromConfig`  | Respond with the [stateless controllers](StatelessControllers.md#responding-to-requests) of a `Config` |
| `ActionResponderProvider\Chain`       | Try several providers in order, first match wins              |
| `ActionResponderProvider\WithMiddleware` | Decorate every responder with [middleware](Middleware.md)  |

## Static map

```php
<?php

use ICanBoogie\Routing\ActionResponderProvider\Immutable;

$responders = new Immutable([
    'articles:list' => new ListArticles(),
    'articles:show' => new ShowArticle(),
]);
```

## PSR-11 container

`Container` looks up the action as a service identifier. Aliases map several actions to the same
service, which suits [action controllers](ActionControllers.md):

```php
<?php

use ICanBoogie\Routing\ActionResponderProvider\Container;

/* @var \Psr\Container\ContainerInterface $container */

$responders = new Container($container, aliases: [
    'articles:list' => ArticleController::class,
    'articles:show' => ArticleController::class,
]);
```

> [!NOTE]
> A controller has state and handles a single request. Make sure the container returns a fresh
> instance on each call (e.g. a "factory" service) for controllers.

## Combining providers

```php
<?php

use ICanBoogie\Routing\ActionResponderProvider\Chain;
use ICanBoogie\Routing\ActionResponderProvider\WithMiddleware;
use ICanBoogie\Routing\Middleware\Alter;
use ICanBoogie\Routing\MiddlewareCollection;

/* @var \ICanBoogie\Routing\ActionResponderProvider $closures, $controllers */

$responders = new WithMiddleware(
    new Chain($closures, $controllers),
    new MiddlewareCollection([ new Alter() ])
);
```



[ActionResponderProvider]: ../lib/ActionResponderProvider.php
