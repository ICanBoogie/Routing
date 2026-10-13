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

A string is resolved with [RouteProvider\ByIdOrAction][]: with `Memoize` and `Compiled`, a route `id`
wins over an `action`, whereas a plain provider returns the first route that matches either, so
keep identifiers unique and distinct from the actions. An
action can be shared by multiple routes, e.g. a method reached through two patterns, in which case the
first route found is used. Giving routes an explicit `id` lets you pick one, and is a good way to keep
URL generation stable if actions are renamed:

```php
$routes = new Immutable([
    new Route('/articles/<id:\d+>', 'articles:show', id: 'article'),
]);
```

## Path parameters

`path_params` is an array or an object whose keys/properties match the placeholders of the pattern.
Values are percent-encoded with `rawurlencode()`, and decoded again when the URL is matched. An object implementing [ToSlug][] is turned into its slug:

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
- `PatternValueMissing` — a `path_params` value is missing for one of the placeholders.
- `PatternValueNotValid` — a `path_params` value doesn't satisfy the constraint of its placeholder.

Both implement `ICanBoogie\Routing\Exception`, see [Exceptions](Exceptions.md).



[UrlGenerator]: ../lib/UrlGenerator.php
[ToSlug]: ../lib/ToSlug.php
[RouteProvider\ByIdOrAction]: ../lib/RouteProvider/ByIdOrAction.php
