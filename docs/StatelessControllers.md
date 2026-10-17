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

| Attribute   | Reads                                                                        |
|-------------|------------------------------------------------------------------------------|
| `#[Path]`   | A placeholder of the pattern (optional, it is the default)                   |
| `#[Query]`  | A value of the query string                                                  |
| `#[Part]`   | A named piece of the body: a form field, or a property of a JSON object      |
| `#[Upload]` | A file uploaded with a `multipart/form-data` form, an `HTTP\File` (optional) |
| `#[Body]`   | The raw body, the `SplFileObject` of `Request::$body_stream`                 |

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

`#[Part]` reads `Request::$request_params`. PHP fills it from `$_POST` for
`application/x-www-form-urlencoded` and `multipart/form-data`, but **nobody decodes
`application/json`**: `icanboogie/http` leaves the body untouched, so for a JSON request
`request_params` is empty and every `#[Part]` is missing. To use `#[Part]` with JSON, decode the body
into `request_params` before routing, see [Reading a JSON body](#reading-a-json-body). Otherwise read
the body with a [decoder](#decoders) bound to `#[Body]`, which suits a JSON body that makes an object
(a command, a DTO) better than one `#[Part]` per property.

Values are cast like path values. A JSON body also provides numbers and booleans, which are accepted
when they have the type of the parameter.

A parameter typed `array` receives a list of values, such as the `tags[]` field of a form or
`?tags[]=a&tags[]=b`. PHP can't declare the type of the elements in a signature, so `of` does:

```php
<?php

use ICanBoogie\Routing\Attribute\ElementType;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Query;

#[Post('/posts')]
public function create(
    #[Part] array $meta,                                   // as the client sent it
    #[Part(of: ElementType::String)] array $tags,          // tags[]=php&tags[]=routing
    #[Part(of: ElementType::Int)] array $category_ids,
    #[Part(of: Status::class)] array $statuses,            // a backed enum
    #[Query(of: ElementType::Int)] array $ids = [],        // ?ids[]=1&ids[]=2
): Response
```

`of` is an `ElementType` case, `String`, `Int`, `Float`, `Bool`, or `Mixed`, or the class of a backed
enum. Elements are cast like a single value, and the keys are preserved: `tags[]` makes a list,
`tags[a]` a map. A value that is not an array, or an element that cannot be cast, or that is an array
itself, makes the request not valid. A missing array follows the rules of any other value, use
`= []` for an optional one.

Without `of`, the default is `ElementType::Mixed`: the array is provided as the client sent it, with
its nested arrays and, in JSON, its numbers and booleans. Nothing is validated, so a controller that
expects strings must say so with `ElementType::String`, otherwise `tags[a][]=x` reaches it as a nested
array. `of` is only valid for a parameter typed `array`, and an array cannot be decoded with
`using`.

A value that is missing, or `null`, is the default value of the parameter, or `null` when it is
nullable. Otherwise, the request is not valid. An empty string is a value, an `int` parameter
doesn't accept it. A path parameter, unlike the others, is always checked against the pattern when
the configuration is built, whereas a value of the query string or of the body is the client's to
provide.

`#[Upload]` reads `Request::$files`, and the parameter must be typed `ICanBoogie\HTTP\File`. Since
a `File` can only come from there, the attribute is optional, like `#[Path]`: use it to name the
file when it is not the name of the parameter, `#[Upload('photo')] File $image`. A file
that was not sent is missing, which includes the form field a browser submits when "no file" was
chosen (`UPLOAD_ERR_NO_FILE`): `null` when the parameter is nullable, otherwise the request is not
valid. A file that failed to upload, such as one over `upload_max_filesize`, or that is empty, is
never missing, it makes the request not valid, even if the parameter is nullable. Repeated fields
(`photos[]`) are not supported. The controller receives a valid file, it only has to move it.

```php
<?php

use ICanBoogie\HTTP\File;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Upload;

#[Post('/photos')]
public function create(#[Part] string $title, File $image): Response
{
    // the image is required
}

#[Post('/photos/:id')]
public function update(int $id, #[Upload('photo')] ?File $image): Response
{
    // the image is optional
}
```

Tests build the request with `Request::OPTION_FILES`, e.g. `new FileList([ 'image' => new File(...) ])`,
or call the method with a `File`.

`#[Body]` is for what `#[Part]` can't provide: XML, a custom format, or a large upload to process
without loading it in memory. The parameter is a `SplFileObject`, which tests can replace with the
`body_stream` option of the request, a method has at most one, and the
body is never missing, an empty body is an empty file. For a form, use `#[Part]`; for a JSON object, use
`#[Part]` once the body is decoded into `request_params`, or a decoder on `#[Body]`.

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

### Reading a JSON body

A middleware can decode a JSON body into `request_params`, after which `#[Part]` works the same for
a form and for JSON. The value of a malformed body is a 400, not a 500:

```php
<?php

use ICanBoogie\HTTP\ClientError;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;
use JsonException;

final class DecodeJsonBody implements Middleware
{
    public function responder(Responder $next): Responder
    {
        return new class ($next) implements Responder {
            public function __construct(private Responder $next)
            {
            }

            public function respond(Request $request): Response
            {
                if ($request->headers->content_type->type === 'application/json' && $request->body_stream) {
                    $request->body_stream->rewind();

                    $json = '';

                    while (!$request->body_stream->eof()) {
                        $json .= $request->body_stream->fread(8192);
                    }

                    try {
                        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw new ClientError("The body is not valid JSON.", previous: $e);
                    }

                    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
                        throw new ClientError("Expected a JSON object.");
                    }

                    $request->request_params = $data;
                }

                return $this->next->respond($request);
            }
        };
    }
}
```

The stream is rewound by the next reader, so `#[Body]` still gets the whole body.

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

A decoder can serve many types by returning `object` and reading `EncodedValue::$into_class`, the
class of the parameter. This one deserializes a JSON body into whatever class the action expects:

```php
<?php

final class JsonBody implements Decoder
{
    public static function decode(EncodedValue $value): object
    {
        $class = $value->into_class;

        return $value->value instanceof SplFileObject
            ? MyDeserializer::deserialize($value->value->fread(…), $class)
            : throw new NotDecodable("Expected the body of the request");
    }
}

#[Post('/articles')]
public function create(#[Body(using: JsonBody::class)] CreateArticle $command): Response

#[Put('/articles/:id')]
public function update(int $id, #[Body(using: JsonBody::class)] UpdateArticle $command): Response
```

- The return type of `decode()` must be a class assignable to the type of the parameter, narrow it.
  Mistakes are found when the configuration is built. A decoder that serves many types, e.g. one
  that deserializes JSON, may return `object` and read the class to produce from
  `EncodedValue::$into_class`; its result is not checked until the action is called.
- Decoders are static: pure functions of their input, without services. When the object needs a
  service, read the value and use the service in the action.
- `EncodedValue` tells the `value`, the `source` (`path`, `query`, `part`, or `body`), the
  `into_class` (the class of the parameter, `object` if it is typed `object`), and the
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
