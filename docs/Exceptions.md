# Exceptions

The exceptions defined by the package implement the `ICanBoogie\Routing\Exception` interface,
so that they're easy to recognize:

```php
<?php

try
{
    // …
}
catch (\ICanBoogie\Routing\Exception $e)
{
    // a routing exception
}
catch (\Throwable $e)
{
    // another type of exception
}
```

The following exceptions are defined:

- [ActionNotDefined][]: Thrown in an attempt to handle a route without action.
- [InvalidPattern][]: Thrown when a pattern is blank or cannot be parsed.
- [PatternRequiresValues][]: Thrown when formatting a pattern that has placeholders without values.
- [RouteNotFound][]: Thrown when no route matches a predicate, for instance when generating a URL.
  It extends `ICanBoogie\HTTP\NotFound` and exposes the `predicate` that failed.



[ActionNotDefined]: ../lib/Exception/ActionNotDefined.php
[ActionTrait]:      ../lib/Controller/ActionTrait.php
[InvalidPattern]:   ../lib/Exception/InvalidPattern.php
[PatternRequiresValues]: ../lib/PatternRequiresValues.php
[RouteNotFound]:   ../lib/Exception/RouteNotFound.php
