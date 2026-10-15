# Stateless controllers

A controller is a plain class: a constructor for the services it needs, and public methods for its
actions. It doesn't extend a base class, doesn't implement an interface, and doesn't hold the
request, so a single instance can serve any number of requests.

```php
<?php

namespace App\Billing;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Post;

final class InvoicesController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
    ) {
    }

    #[Get('/invoices')]
    public function list(): Response
    {
        // ...
    }

    #[Get('/invoices/:id')]
    public function show(int $id): Response
    {
        $invoice = $this->invoices->find($id);

        // ...
    }

    #[Post('/invoices')]
    public function create(): Response
    {
        // ...
    }
}
```

The routes come from the attributes, the arguments of `show()` come from the request, and the
actions are inferred: `invoices:list`, `invoices:show`, and `invoices:create`. Everything is
analyzed once, when the configuration is built, so that mistakes are reported right away and
nothing is reflected when requests are handled.

> [!NOTE]
> This is the way to write new controllers. [ControllerAbstract](Controllers.md) and
> [action controllers](ActionControllers.md) keep working, they are described in their own pages. The
> design is explained in [ADR 0001](adr/0001-stateless-controllers.md).

- [Defining routes](#defining-routes)
- [Actions](#actions)
- [Parameters](#parameters)
- [Return values](#return-values)
- [Building the configuration](#building-the-configuration)
- [Responding to requests](#responding-to-requests)
- [Failures](#failures)



## Defining routes

Routes are defined by the `Route` attribute on the methods of the controller. It is repeatable, a
method can be reached through multiple routes. A shortcut exists for each request method: `Get`,
`Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`, `Connect`, and `Trace`. Patterns follow the
[usual syntax](RoutePatterns.md).

```php
<?php

use ICanBoogie\HTTP\RequestMethod;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Route;

#[Get('/invoices/:id', id: 'invoice')]
#[Route('/factures/:id', methods: [ RequestMethod::METHOD_GET, RequestMethod::METHOD_HEAD ])]
public function show(int $id): Response
```

### Prefixes

The `Prefix` attribute of the class defines what the routes of a controller share. Both arguments
are optional, but at least one is required. They are concatenated before the pattern and the action
of each route, so `/invoices/:id` with the action `invoices:show` becomes `/billing/invoices/:id`
and `billing:invoices:show`.

```php
<?php

use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Prefix;

#[Prefix(pattern: '/billing', action: 'billing')]
final class InvoicesController
{
    #[Get('/invoices/:id')]
    public function show(int $id): Response
    {
        // ...
    }
}
```

The pattern of a shortcut can be empty when the prefix defines the pattern:

```php
#[Prefix(pattern: '/invoices')]
final class InvoicesController
{
    #[Get('')]
    public function list(): Response // GET /invoices
    {
    }

    #[Get('/:id')]
    public function show(int $id): Response // GET /invoices/:id
    {
    }
}
```

The prefix pattern may contain placeholders, they become path parameters of every method of the
controller.

### Without attributes

A `RouteBuilder` defines the same things in code, it is the second argument of
`ConfigBuilder::add_controller()`. It offers `prefix()`, `route()`, and a method for each request
method. Define the prefix with the attribute or with the builder, not both.

```php
<?php

use ICanBoogie\Routing\ConfigBuilder;
use ICanBoogie\Routing\RouteBuilder;

$builder = new ConfigBuilder()
    ->add_controller(InvoicesController::class, fn(RouteBuilder $r) => $r
        ->prefix(pattern: '/billing', action: 'billing')
        ->get('/invoices', 'list')
        ->get('/invoices/:id', 'show')
        ->post('/invoices', 'create'));
```

Attributes and the builder can be mixed, their routes are merged.



## Actions

The action of a route identifies it, and is what the `UrlGenerator` and the
[action responder providers](ActionResponders.md) work with. When a route doesn't specify one, it is
inferred from the name of the controller and the name of the method, used as is:

| Controller            | Method      | Action               |
|-----------------------|-------------|----------------------|
| `InvoicesController`  | `show`      | `invoices:show`      |
| `ContactController`   | `show`      | `contact:show`       |
| `UserPostsController` | `show`      | `user-posts:show`    |
| `InvoicesController`  | `by_status` | `invoices:by_status` |

The name of the controller is its class name without the `Controller` suffix, hyphenated. It is
not pluralized: the class name is what you get, so `ContactController` is a single resource and
`InvoicesController` a collection. Use `action:` to choose the action of a route, `Prefix` to
prepend a namespace, and `Name` to replace the name of the controller.

```php
#[Get('/invoices/:id', action: 'invoice:display')]
```

### Names

The `Name` attribute of the class replaces the name inferred from the class, it is used as is. It
is useful when the class doesn't follow the convention, e.g. a suffix other than `Controller`
(only `Controller` is stripped), or a name that you'd rather not have in the class. The name cannot
be blank or contain `:`.

```php
<?php

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Name;

#[Name('contact')]
final class ContactPage
{
    #[Get('/contact')]
    public function show(): Response // contact:show, instead of contact-page:show
}
```

Without attributes, use the parameter `name:` of `ConfigBuilder::add_controller()`. Defining the
name with both is an error.

```php
$builder->add_controller(ContactPage::class, name: 'contact');
```

An action belongs to a single method of a single controller. Two methods with the same action are an
error.

### Route identifiers

A method can be reached through multiple routes, and they all share its action. The action says what
answers the request, it can't tell the routes apart, so generating a URL from `invoices:list` always
gives the first route. Give the routes an `id` to ask for a specific one:

```php
<?php

#[Get('/invoices', id: 'invoices')]
#[Get('/invoices/page/:page', id: 'invoices-page')]
public function list(int $page = 1): Response
```

```php
$url_generator->generate_url('invoices');                         // "/invoices"
$url_generator->generate_url('invoices-page', [ 'page' => 2 ]);   // "/invoices/page/2"
```

The `id` is optional and only used to find a route, see [URL generation](UrlGeneration.md). It also
keeps generated URLs stable when an action is renamed. `ConfigBuilder` rejects an `id`
that another route already uses, or that is the action of another route, with a `ConfigNotValid`,
because a string that is both would be ambiguous: `Memoize` and `Compiled` choose the `id`, whereas
a plain provider chooses the first route that matches either. It can only check the routes it
defines, not the ones added by hand to a provider chained next to `$config->routes`.



## Parameters

The arguments of an action are produced from the request, by name and by type. A parameter typed
`Request` receives the request, a parameter typed `Route` receives the route that matched, and any
other parameter is a path parameter.

```php
<?php

public function show(Request $request, Route $route, int $id): Response
```

A path parameter is found by its name in the pattern, not by its position, and cast to its
type: `int`, `float`, `string`, `bool`, or a backed enum. Reordering the placeholders can't pass
the wrong values.

| Type     | Accepts                                                         |
|----------|-----------------------------------------------------------------|
| `int`    | An integer, leading zeros included (`007` is `7`), within range |
| `float`  | A finite number                                                 |
| `string` | Anything                                                        |
| `bool`   | `1`, `0`, `true`, or `false`                                    |
| enum     | A backing value of a `BackedEnum`, e.g. `draft`                 |

```php
<?php

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}

#[Get('/invoices/status/:status')]
public function by_status(Status $status): Response
```

The catch-all `*` of a pattern is a parameter named `all`.

### Where values come from

A parameter is bound to the part of the request it comes from, which is explicit in the signature:

| Attribute  | Reads                                                                   |
|------------|-------------------------------------------------------------------------|
| `#[Path]`  | A placeholder of the pattern (optional, it is the default)              |
| `#[Query]` | A value of the query string                                             |
| `#[Part]`  | A named piece of the body: a form field, or a property of a JSON object |
| `#[Body]`  | The raw body, the `SplFileObject` of `Request::$body_stream`            |

The name of the value is the name of the parameter, give another one to the attribute when they
differ. A parameter has a single source.

```php
<?php

use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Path;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Query;

#[Get('/search/:scope')]
public function search(string $scope, #[Query('q')] string $term, #[Query] int $page = 1): Response
{
    // GET /search/books?q=php&page=2
}

#[Post('/invoices/:id/notes')]
public function add_note(#[Path('id')] int $invoice_id, #[Part] string $text, #[Part] ?bool $public): Response
{
    // POST /invoices/12/notes, with text=… as a form, or as JSON {"text": "…", "public": true}
}
```

Values are cast like path values. A JSON body also provides numbers and booleans, which are accepted
when they have the type of the parameter. Arrays are not supported, a value is a single value.

A value that is missing, or `null`, is the default value of the parameter, or `null` when it is
nullable. Otherwise, the request is not valid. An empty string is a value, an `int` parameter
doesn't accept it. A path parameter, unlike the others, is always checked against the pattern when
the configuration is built, whereas a value of the query string or of the body is the client's to
provide.

`#[Body]` is for what `#[Part]` can't provide: XML, a custom format, or a large upload to process
without loading it in memory. The parameter is a `SplFileObject`, which tests can replace with the
`body_stream` option of the request, a method has at most one, and the
body is never missing, an empty body is an empty file. For a form or a JSON object, use `#[Part]`.

```php
<?php

#[Put('/invoices/:id/import')]
public function import(int $id, #[Body] SplFileObject $body): Response
{
    foreach ($body as $line) {
        // …
    }
}
```

`#[Query]` and `#[Part]` never read the path, and `#[Path]` never reads the query string, so
`?id=2` can't take the place of a placeholder or fill in what the body left out.

### Decoders

A parameter can be an object, made from the value by a decoder: a class that implements
`ICanBoogie\Routing\Decoder`, with a static `decode()` method. Give it to the attribute
with `using`.

```php
<?php

use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use ICanBoogie\Routing\Exception\NotDecodable;

final class SlugDecoder implements Decoder
{
    public static function decode(EncodedValue $value): Slug
    {
        return is_string($value->value) && preg_match('/^[a-z0-9-]+$/', $value->value)
            ? new Slug($value->value)
            : throw new NotDecodable("Not a slug");
    }
}

#[Get('/articles/:slug')]
public function show(#[Path(using: SlugDecoder::class)] Slug $slug): Response
```

`using` works with `#[Path]`, `#[Query]`, `#[Part]`, and `#[Body]`. It is optional when the type
of the parameter is a decoder itself, a class that decodes itself:

```php
<?php

final readonly class Slug implements Decoder
{
    public static function decode(EncodedValue $value): self { /* … */ }
}

#[Get('/articles/:slug')]
public function show(Slug $slug): Response
```

- The return type of `decode()` must be a class assignable to the type of the parameter, `object` is
  not enough, narrow it. Mistakes are found when the configuration is built.
- Decoders are static: pure functions of their input, without services. When the object needs a
  service, read the value and use the service in the action.
- `EncodedValue` tells the `value`, the `source` (`path`, `query`, `part`, or `body`), and the
  `name`, `null` for the body. The value is a string for a path parameter. For the query string and
  the form or JSON body it is whatever the client provided under the name: a string, a number or a
  boolean in JSON, or an array. For `#[Body]` it is the `SplFileObject` of the raw body, and the
  decoder reads it as it needs, e.g. `#[Body(using: ImportDecoder::class)] Import $import`. The
  decoder checks and casts the value as it needs.
- A decoder is never called for a missing value, or a `null`. The parameter gets its default value
  or `null` when nullable, and the request is not valid otherwise. The default must be `null`, a
  scalar, an array, or an enum case, so that the configuration can be exported; make the parameter
  nullable and apply an object default in the action.
- A decoder rejects a value by throwing `Exception\NotDecodable`. `InvalidArgumentException` and
  `ValueError`, e.g. thrown by `Status::from()`, are treated the same way, so a method that already
  throws them can be used as is. They are reported like a value that cannot be cast: a 404 for a
  path value, a 400 otherwise. Any other exception is a bug and propagates.

### Optional parameters

A path parameter must be a placeholder of every route of its method, unless it has a default value
or is nullable, in which case the value is the default, or `null`, when the placeholder is not part
of the route that matched. It must be a placeholder of at least one route.

```php
<?php

#[Get('/invoices')]
#[Get('/invoices/page/:page')]
public function list(int $page = 1): Response
```

A parameter that cannot be bound fails when the configuration is built, with the class, the method,
and the parameter in the message, e.g.
"`InvoicesController::show()` parameter `$slug`: no `:slug` in `/invoices/:id`."



## Return values

An action returns a `Response`, or an `IntoResponse`, an object that knows how to turn itself into
one. It must be declared as the return type of the method, `string`, `null`, and `void` are
rejected when the configuration is built.

```php
<?php

use ICanBoogie\Routing\IntoResponse\Json;

#[Get('/invoices/:id')]
public function show(int $id): Json
{
    return new Json($this->invoices->find($id));
}
```

It is how wrappers such as views or payloads can be returned by actions that don't need to know
about HTTP.

### JSON responses

`IntoResponse\Json` turns data into an `application/json` response.

```php
<?php

use ICanBoogie\Routing\IntoResponse\Json;

new Json([ 'id' => 1 ], status: 201);
```

By default, arrays and scalars are encoded with `json_encode()`. Objects, even nested in an array,
must implement `JsonSerializable`, otherwise an `InvalidArgumentException` is thrown, so that the
public properties of an entity are never exposed by accident. A class decides what it exposes in
`jsonSerialize()`.

#### Using a serializer

Provide `encode`, a closure that turns a value into a JSON string, to use a serializer such as
[Crell/Serde](https://github.com/Crell/Serde) or the Symfony Serializer. The library doesn't depend
on any of them. With a custom encoder, you decide what is encoded, objects don't have to implement
`JsonSerializable`.

```php
<?php

use ICanBoogie\Routing\IntoResponse\Json;

new Json($invoice, encode: fn(object $o): string => $serde->serialize($o, format: 'json'));
```

You can also use a static helper:

```php
<?php

use Crell\Serde\SerdeCommon;
use ICanBoogie\Routing\IntoResponse\Json;

final class JsonEncoder
{
    private static ?SerdeCommon $serde = null;

    /**
     * Encodes a single value into JSON.
     */
    public static function encode(mixed $value): string
    {
        self::$serde ??= new SerdeCommon();

        return self::$serde->serialize($value, format: 'json');
    }
}

// ...

new Json($invoice, encode: JsonEncoder::encode(...));
```

A list is encoded item by item: the encoder is called for each item and the results are joined, so
it only ever deals with one value, e.g. one object. A keyed array is passed to the encoder as a
whole.

```php
<?php

// $serde->serialize() is called for each invoice, the response body is "[{…},{…}]"
new Json($invoices, encode: fn(object $o): string => $serde->serialize($o, format: 'json'));
```

#### Tweaking the response

Provide `tweak`, a closure that receives the response, to change what `Json` sets by default,
e.g. the `Cache-Control` header. It is called last, after the status and `Content-Type` are set,
so it has the last word.

```php
<?php

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\IntoResponse\Json;

new Json($invoices, tweak: fn(Response $r) => $r->headers->cache_control->max_age = 60);
```



## Building the configuration

`ConfigBuilder` collects the controllers and builds a readonly `Config`. It validates and resolves
everything as it goes, and nothing is registered when a controller is not valid.

```php
<?php

use ICanBoogie\Routing\ConfigBuilder;

$config = new ConfigBuilder()
    ->add_controller(InvoicesController::class)
    ->add_controller(ContactController::class, fn(RouteBuilder $r) => $r
        ->get('/contact.html', 'new')
        ->post('/contact.html', 'create'))
    ->build();

$config->routes; // a RouteProvider
```

### Discovering controllers

Instead of adding each controller with `add_controller()`, call `enable_discovery()` and let the
builder find them. When the configuration is built, every class with a method marked with a `Route`
attribute (`Get`, `Post`…) is added as a controller. `disable_discovery()` turns it off again.

Discovery relies on [olvlvl/composer-attribute-collector][], which your application has to require.
Load the `vendor/attributes.php` file it generates before building the configuration, see its
documentation.

A controller added with `add_controller()` is never discovered a second time, whether you add it
before or after enabling discovery. Use this to add a controller by hand, with a `RouteBuilder` for
instance, while discovering the others.

```php
<?php

$config = new ConfigBuilder()
    ->enable_discovery()
    ->build();
```

### Serving routes

`cache_strategy()` defines how the routes are served:

| `CacheStrategy` | Routes are…                                  | Use it for  |
|-----------------|----------------------------------------------|-------------|
| `NONE`          | Tested one after the other (default)         | Tests       |
| `MEMOIZE`       | Indexed in memory by `RouteProvider\Memoize` | Development |
| `COMPILE`       | Compiled by `RouteProvider\Compiled`         | Production  |

See [Route providers](RouteProviders.md) for what they do.

### Exporting the configuration

In production, the configuration is built once and exported, so that requests don't pay for
reflecting the controllers or building the routes. `Config` and everything it holds implement
`__set_state()`, they can be exported with `var_export()` and loaded from a PHP file, which OPcache
keeps in memory.

```php
<?php

use ICanBoogie\Routing\CacheStrategy;
use ICanBoogie\Routing\ConfigBuilder;

$config = new ConfigBuilder()
    ->enable_discovery()
    ->cache_strategy(CacheStrategy::COMPILE)
    ->build();

$tmp = 'config.php.' . uniqid();
file_put_contents($tmp, '<?php return ' . var_export($config, true) . ';');
rename($tmp, 'config.php');

$config = require 'config.php';
```

> [!NOTE]
> A configuration built with `CacheStrategy::MEMOIZE` can't be exported. In development, build the
> configuration on each request instead, there is nothing to invalidate.



## Responding to requests

`ActionResponderProvider\FromConfig` provides the responders of the controllers of a `Config`. It
creates one `MethodResponder` per controller, when one of its actions is first requested. The
responder finds the method with the action of the matched route, produces the arguments from the
request, and returns the response of the method.

The controllers are obtained through a closure. It is where services are wired, and where you decide
whether a controller is shared or created for the occasion.

```php
<?php

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder\DelegateToProvider;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\RequestResponderProvider;

/* @var \ICanBoogie\Routing\Config $config */
/* @var \Psr\Container\ContainerInterface $container */

$responders = new FromConfig($config, fn(string $class): object => $container->get($class));

$application = new DelegateToProvider(new RequestResponderProvider($config->routes, $responders));

$response = $application->respond(Request::from($_SERVER));
$response();
```

`FromConfig` is an [ActionResponderProvider](ActionResponders.md) like the others, so it composes
with `Chain`, to serve the actions of legacy controllers or closures next to the new ones, and with
`WithMiddleware`, to wrap every action in [middleware](Middleware.md).

```php
<?php

use ICanBoogie\Routing\ActionResponderProvider\Chain;
use ICanBoogie\Routing\ActionResponderProvider\FromConfig;
use ICanBoogie\Routing\ActionResponderProvider\WithMiddleware;

$responders = new WithMiddleware(
    new Chain(new FromConfig($config, $factory), $legacy),
    $middleware
);
```

> [!NOTE]
> Routes of legacy responders are not defined by `ConfigBuilder`, add them to a
> `RouteProvider\Mutable` or `Immutable` and put it in a `RouteProvider\Chain` next
> to `$config->routes`.



## Failures

Failures are reported by exceptions that the HTTP stack turns into responses.

| Exception                         | When                                                                                | Status |
|-----------------------------------|-------------------------------------------------------------------------------------|--------|
| `Exception\PathParameterNotValid` | A path value cannot be cast or decoded, e.g. `/invoices/abc` for an `int $id`       | 404    |
| `Exception\ParameterNotValid`     | A value of the query string or of the body is missing, or cannot be cast or decoded | 400    |
| `Exception\ConfigNotValid`        | A mistake in the controllers, found when the configuration is built                 | none   |

A path value that cannot be cast designates a resource that doesn't exist, hence the 404.
`PathParameterNotValid` extends `NotFound`, and tells the `name` and the `type` of the parameter.
Any other value comes from the client and is not valid, hence the 400. `ParameterNotValid` extends
`ClientError`, and tells the `source` (`query`, `part`, or `body`), the `name`, the `type`, and
whether the value was `missing`.

`ConfigNotValid` is a `LogicException`, it is never thrown when handling requests.



[olvlvl/composer-attribute-collector]: https://github.com/olvlvl/composer-attribute-collector
