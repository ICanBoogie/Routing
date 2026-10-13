# Route patterns

A route is represented with a [Route][] instance. Two parameters are required to create an instance:
`pattern` and `action`. `pattern` is the pattern to match or generate a URL. `action` is an
identifier for an action, which can be used to match with a Responder.

A pattern is used to match a URL with a route. Placeholders may be used to match multiple URL to a
single route and extract its parameters. Three types of placeholder are available:

- Relaxed placeholder: Only the name of the parameter is specified, it matches anything until
  the following part. e.g. `/articles/:id/edit`. The character that follows ends the capture, so
  it can't appear in the value: in `/:category-:slug` a category can't contain `-`, and `/` always
  ends a capture. Choose a separator your values never contain, or use a constrained placeholder.

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

A [Pattern][] works in both directions. `Pattern::from()` parses a string (results are shared while something holds them, e.g. a route),
`matches()` extracts the parameters, and `format()` builds a URL. Values are percent-encoded by
`format()` and percent-decoded by `matches()`, after the regex ran: constraints see the raw path, and an
encoded `/` (`%2F`) stays inside its segment. See
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
Formatting a pattern that has placeholders without values throws `PatternRequiresValues`, and a missing
(or `null`) value for one of them throws `PatternValueMissing`. The joker `*` captures a parameter named
`all`; when formatting, its `/` are kept. A parameter name can only be used once in a pattern.

A value must satisfy the constraint of its parameter, otherwise `format()` throws
`PatternValueNotValid`: the URL would not match the pattern. The constraint is tested against the
encoded value, because that is what the matcher sees. The character after a relaxed placeholder ends
its capture, so a value that contains it is rejected: with `/blog/:slug.html`, the value `v1.2` is
refused, and with `/:category-:slug`, so is the category `foo-bar`. (Formatting it would give
`/foo-bar-x`, which matches as `foo` and `bar-x`.) Only characters that stay unencoded
(`- _ . ~`) can clash, the others are percent-encoded. An empty value is refused too. To allow such
a value, use a constrained placeholder such as `<slug:[^/]+>.html`.

## Tips

- Prefer constrained placeholders (`<id:\d+>`) over relaxed ones (`:id`) when the type is known; it
  avoids matching unexpected URLs, e.g. `/articles/new` against `/articles/:id`.
- Routes are tried in the order they were added, except with [`Memoize`](RouteProviders.md#performance),
  which tries static routes first.
- Captured parameters are strings. Cast them, or type the action method arguments.

[Route]: ../lib/Route.php
[Pattern]: ../lib/Pattern.php
