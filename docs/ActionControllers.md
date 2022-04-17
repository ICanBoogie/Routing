# Action controllers

Action controllers are used to group related HTTP request handling logic into a class and use HTTP
methods to separate concerns. An action controller can be created easily by extending
[ControllerAbstract][] and using the trait [ActionTrait][].

```php
<?php

use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Controller\ActionTrait;

final class ArticleController extends ControllerAbstract
{
    use ActionTrait;
}
```

Next, we define the methods the controllers needs to handle. For instance, for a route with the
action `articles:show`, one the following methods can be implemented. `{method}` is a placeholder
for the method of the request e.g. `get` or `post`.

- `{method}_articles_show`
- `any_articles_show`
- `articles_show`
- `{method}_show`
- `any_show`
- `show`

Since our controller deals exclusively with articles, let's go with the simplest one: `show`. It's
recommended to is the `private` visibility.

```php
<?php

use ICanBoogie\Routing\ControllerAbstract;
use ICanBoogie\Routing\Controller\ActionTrait;

final class ArticleController extends ControllerAbstract
{
    use ActionTrait;

    private function show(): string
    {
        // …
    }
}
```

## Resource controllers

With [ActionTrait][] and [RouteMaker][] resource controllers can be created with a minimum
boilerplate.

The following examples demonstrates how to create "resource" routes for "articles":

```php
<?php

use ICanBoogie\Routing\RouteMaker;

$routes = RouteMaker::resource('articles');
```

The following table list the verbs/routes and their corresponding action. `{name}` is the
placeholder for the plural name of the resource, while `{id}` is the placeholder for the resource
identifier.

| HTTP verb | Path                | Action        | Used for                                   |
| --------- | ------------------- |---------------| ------------------------------------------ |
| GET       | `/{name}`           | {name}:list   | A list of `{resource}`                     |
| GET       | `/{name}/new`       | {name}:new    | A form for creating a new `{resource}`     |
| POST      | `/{name}`           | {name}:create | Create a new `{resource}`                  |
| GET       | `/{name}/{id}`      | {name}:show   | A specific `{resource}`                    |
| GET       | `/{name}/{id}/edit` | {name}:edit   | A form for editing a specific `{resource}` |
| PATCH/PUT | `/{name}/{id}`      | {name}:update | Update a specific `{resource}`             |
| DELETE    | `/{name}/{id}`      | {name}:delete | Delete a specific `{resource}`             |

The routes listed are more of a guideline than a requirement, still the actions are important. Such
routes can easily be created with `RouteMaker`:

The following example demonstrates how the resource controller for _articles_ may be implemented.
The example implements all actions, but you are free to implement only some of them.

```php
<?php

use ICanBoogie\Routing\Controller\ActionTrait;
use ICanBoogie\Routing\ControllerAbstract;

class ArticleController extends ControllerAbstract
{
    use ActionTrait;

    private function list(Request $request)
    {
        // …
    }

    private function new(Request $request)
    {
        // …
    }

    private function create(Request $request)
    {
        // …
    }

    private function show(Request $request, int $id)
    {
        // …
    }

    private function edit(Request $request, int $id)
    {
        // …
    }

    private function update(Request $request, int $id)
    {
        // …
    }

    private function delete(Request $request, int $id)
    {
        // …
    }
}
```



[Controller]:   ../lib/ControllerAbstract.php
[ActionTrait]:  ../lib/Controller/ActionTrait.php

[RESTful]: https://en.wikipedia.org/wiki/Representational_state_transfer
