# URL generation

Routes map URLs to actions, and back. [UrlGenerator][] builds a URL from a route identifier, an
action, or a predicate:

```php
<?php

use ICanBoogie\Routing\RouteProvider;
use ICanBoogie\Routing\UrlGenerator\UrlGeneratorWithRouteProvider;

/* @var RouteProvider $routes */

$url_generator = new UrlGeneratorWithRouteProvider($routes);

// By action (or identifier)
$url_generator->generate_url('articles:show', [ 'id' => 123 ]);
// "/articles/123"

// With query parameters
$url_generator->generate_url('articles:list', query_params: [ 'page' => 2 ]);
// "/articles?page=2"

// With a predicate
$url_generator->generate_url(fn($route) => $route->action === 'articles:edit', [ 'id' => 123 ]);
// "/articles/123/edit"
```

A string is resolved with [RouteProvider\ByIdOrAction][]: a route `id` wins over an `action`. Giving
routes an explicit `id` is a good way to keep URL generation stable if actions are renamed:

```php
$routes = (new RouteCollector())
    ->get('/articles/<id:\d+>', 'articles:show', id: 'article')
    ->collect();
```

## Path parameters

`path_params` is an array or an object whose keys/properties match the placeholders of the pattern.
Values are URL-encoded. An object implementing [ToSlug][] is turned into its slug:

```php
<?php

use ICanBoogie\Routing\ToSlug;

final class Article implements ToSlug
{
    public function __construct(public int $id, private string $title)
    {
    }

    public function to_slug(): string
    {
        return strtolower(str_replace(' ', '-', $this->title));
    }
}

// pattern: /blog/<id:\d+>-:slug
$url_generator->generate_url('blog:show', [ 'id' => 12, 'slug' => $article ]);
```

Anonymous placeholders (`<\d+>`) are addressed by index (`[ 0 => 123 ]`).

## Errors

- `RouteNotFound` — no route matches. The failing predicate is available as `$e->predicate`.
- `PatternRequiresValues` — the route has placeholders but no `path_params` were provided.

Both implement `ICanBoogie\Routing\Exception`, see [Exceptions](Exceptions.md).



[UrlGenerator]: ../lib/UrlGenerator.php
[ToSlug]: ../lib/ToSlug.php
[RouteProvider\ByIdOrAction]: ../lib/RouteProvider/ByIdOrAction.php
