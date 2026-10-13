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
- [PatternValueNotValid][]: Thrown when formatting a pattern with a value that doesn't satisfy the constraint of its placeholder.
- [PatternValueMissing][]: Thrown when formatting a pattern without a value for one of its placeholders.
- [ConfigNotValid][]: Thrown when building the configuration of [stateless controllers](StatelessControllers.md)
  finds a mistake, for instance a parameter that is not in the pattern. It is a `LogicException`.
- [ParameterNotValid][]: Thrown when a value of the query string or of the body, bound to a parameter,
  is missing or can't be cast. It extends `ICanBoogie\HTTP\ClientError`, a 400.
- [PathParameterNotValid][]: Thrown when a path value can't be cast to the type of the action's
  parameter, e.g. `/invoices/abc` for an `int $id`. It extends `ICanBoogie\HTTP\NotFound`.
- [RouteNotFound][]: Thrown when no route matches a predicate, for instance when generating a URL.
  It extends `ICanBoogie\HTTP\NotFound` and exposes the `predicate` that failed.



[ActionNotDefined]: ../lib/Exception/ActionNotDefined.php
[ActionTrait]:      ../lib/Controller/ActionTrait.php
[ConfigNotValid]: ../lib/Exception/ConfigNotValid.php
[InvalidPattern]:   ../lib/Exception/InvalidPattern.php
[ParameterNotValid]: ../lib/Exception/ParameterNotValid.php
[PathParameterNotValid]: ../lib/Exception/PathParameterNotValid.php
[PatternRequiresValues]: ../lib/PatternRequiresValues.php
[PatternValueMissing]: ../lib/PatternValueMissing.php
[PatternValueNotValid]: ../lib/PatternValueNotValid.php
[RouteNotFound]:   ../lib/Exception/RouteNotFound.php
