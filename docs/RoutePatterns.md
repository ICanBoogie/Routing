# Route patterns

A route is represented with a [Route][] instance. Two parameters are required to create an instance:
`pattern` and `action`. `pattern` is the pattern to match or generate a URL. `action` is an
identifier for an action, which can be used to match with a Responder.

A pattern is used to match a URL with a route. Placeholders may be used to match multiple URL to a
single route and extract its parameters. Three types of placeholder are available:

- Relaxed placeholder: Only the name of the parameter is specified, it matches anything until
  the following part. e.g. `/articles/:id/edit`.

- Constrained placeholder: A regular expression is used to match the parameter value.
  e.g. `/articles/<id:\d+>/edit` where `<id:\d+>` is the placeholder for the `id` parameter which
  value must matches `/^\d+$/`.

- Anonymous constrained placeholder: Same as the constrained placeholder, except the parameter has
  no name but an index e.g. `/articles/<\d+>/edit` where `<\d+>` in a placeholder which index is 0.

Additionally, the joker character `*`—which can only be used at the end of a pattern—matches
anything. e.g. `/articles/123*` matches `/articles/123` and `/articles/123456` as well.

Finally, constraints RegExp are extended with the following:

- `{:sha1:}`: Matches [SHA-1](https://en.wikipedia.org/wiki/SHA-1) hashes. e.g. `/files/<hash:{:sha1:}>`.
- `{:uuid:}`: Matches [Universally unique identifiers](https://en.wikipedia.org/wiki/Universally_unique_identifier)
  (UUID). e.g. `/articles/<uuid:{:uuid:}>/edit`.

You can use them in any combination:

- `/blog/:year-:month-:slug`
- `/blog/<year:\d{4}>-<month:\d{2}>-:slug`
- `/images/<uuid:{:uuid:}>/<size:\d+x|x\d+|\d+x\d+>*`


## Matching and formatting

A [Pattern][] works in both directions. `Pattern::from()` parses a string (results are cached),
`matches()` extracts the parameters, and `format()` builds a URL. See
[URL generation](UrlGeneration.md) for the higher-level API.

```php
<?php

use ICanBoogie\Routing\Pattern;

$pattern = Pattern::from('/blog/<year:\d{4}>-<month:\d{2}>-:slug.html');

$pattern->matches('/blog/2013-07-this-is-a-test.html', $captured);
// true, $captured = [ 'year' => '2013', 'month' => '07', 'slug' => 'this-is-a-test' ]

$pattern->format([ 'year' => '2013', 'month' => '07', 'slug' => 'this-is-a-test' ]);
// "/blog/2013-07-this-is-a-test.html"
```

Patterns without placeholder are compared as plain strings, which is what makes them fast to match.
Formatting a pattern that has placeholders without values throws `PatternRequiresValues`.

## Tips

- Prefer constrained placeholders (`<id:\d+>`) over relaxed ones (`:id`) when the type is known; it
  avoids matching unexpected URLs, e.g. `/articles/new` against `/articles/:id`.
- Routes are tried in the order they were added, except with [`Memoize`](RouteProviders.md#performance),
  which tries static routes first.
- Captured parameters are strings. Cast them, or type the action method arguments.

[Route]: ../lib/Route.php
[Pattern]: ../lib/Pattern.php
