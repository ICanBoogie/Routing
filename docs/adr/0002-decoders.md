# 2. Decoders

- Status: Accepted. Implemented.
- Date: 2026-10-13
- Deciders: Olivier Laviale
- Amends: [ADR 0001](0001-stateless-controllers.md), "No hydration into classes".

## Context

ADR 0001 binds the parameters of an action to the request with `#[Path]`, `#[Query]`, `#[Part]`
and `#[Body]`. They only produce scalars, backed enums, and the raw body as a file object. A class-typed parameter
is rejected at configuration time, for two reasons given there:

- The library cannot impose a serialization package: PHP has no serde, and Symfony Serializer,
  Valinor, JMS and hand-written mapping all exist.
- Hydration needs a service, which a plan entry cannot hold.

The cost is that every action that wants a `Slug`, an `Email`, or a `CreateArticle` command reads
scalars and builds the object itself, repeating the same mapping and the same error handling. ADR
0001 left the door open to "a possible future opt-in contract", which would need its own ADR. This is
that ADR.

## Decision drivers

- The plan stays plain data that `var_export()` can write, so compiled configurations keep working.
- Controllers and decoders stay stateless (ADR 0001).
- The contract is enforced by PHP, not by a naming convention checked at build time.
- No serialization package is imposed, and none is excluded: a decoder can call any of them.
- A bad value is a client error, mapped like other bad values (404 for the path, 400 elsewhere).
- The contract can evolve without breaking existing decoders.

## Considered options

### A. Static callable arrays

`#[Path(using: [SlugDecoder::class, 'from'])]`. Exportable and free of any interface, but PHP
enforces nothing: the builder has to check that the method exists, is public and is static.

### B. Typed interfaces per kind of input

`StringDecoder`, `ArrayDecoder`, `StreamDecoder`, each with a typed `decode()`. PHP enforces the
signature, but there are three fixed kinds of input, and a new kind needs a new interface.

PHP has no generics, and parameter types are contravariant, so a single interface cannot carry the
input type: `decode(mixed $value)` cannot be implemented as `decode(string $value)`, even when the
interface leaves the type off.

### C. Marker interface with a `decode()` convention

One empty interface, the builder reflects on `decode()` and reads the type of its first parameter,
which would let `Casting` apply automatically. PHP enforces nothing about the method, and static
analysers cannot see the contract.

### D. One interface, an envelope for the input (chosen)

```php
interface Decoder
{
    /**
     * @throws NotDecodable
     */
    public static function decode(EncodedValue $value): object;
}
```

PHP enforces the signature, there is one interface, and the envelope can grow.

### E. D, with a `DecodedValue` wrapper for the output

The wrapper loses the return type, which becomes a `mixed` hole caught only when the action is
called, and costs an allocation per parameter per request. Returning `object` and letting
implementations narrow it (`decode(EncodedValue $v): Slug`) keeps the type visible to static
analysis, and lets the builder check it.

## Decision

Adopt D.

### Usage

`#[Path]`, `#[Query]`, `#[Part]` and `#[Body]` accept `using`, the class of a decoder:

```php
#[Path(using: SlugDecoder::class)] Slug $slug
#[Body(using: CreateArticleDecoder::class)] CreateArticle $command
```

A class-string is plain data, so the plan remains exportable.

When the type of the parameter implements `Decoder` itself, `using` is optional:

```php
final readonly class CreateArticle implements Decoder
{
    public static function decode(EncodedValue $value): self { … }
}

#[Body] CreateArticle $command
```

`using` is for a decoder in a separate class, e.g. when the type shouldn't depend on the routing
library, or to decode the same type in several ways.

### The decoder

- **Decoders are static**: pure functions of their input, with no dependency injection and no
  state. This keeps the plan plain data and agrees with ADR 0001. A decoder that needs a service
  cannot be one; the action reads the values and calls the service. Switching to instance decoders
  later would break the interface, so static is a decision, not a default.
- The return type is `object`, narrowed by implementations.
- `EncodedValue` is an envelope, not a bare `mixed`, so fields can be added without breaking
  decoders.

| Member of `EncodedValue` | Description |
|---|---|
| `$value` | The raw value, see below. |
| `$source` | `path`, `query`, `part` or `body`. |
| `$name` | Name of the value in its source, for error messages. |

The envelope has no cast helpers: what a decoder needs from a value is its own business, and it
does its own checks and casting in PHP. `Casting` stays internal to the scalar parameters. Helpers
can be added later, the envelope exists so that they can.

What `$value` holds depends on the source:

| Source | `$value` |
|---|---|
| `#[Path]` | a `string` |
| `#[Query]`, `#[Part]` | the value under `name`: a `string`, a number or boolean (JSON), or an `array` |
| `#[Body]` | the body stream, the `SplFileObject` of `Request::$body_stream` |

`#[Query]` and `#[Part]` never hand the whole source to the decoder, only the value under `name`.
Anything that needs the whole body uses `#[Body]`, which stays the raw body of ADR 0001, at most
one per method.

### Configuration-time validation

`PlanBuilder` fails the build, naming the class, method and parameter, when:

- `using` is not a class implementing `Decoder`;
- the return type of `decode()` is not assignable to the type of the parameter;
- the type of the parameter is neither a scalar, a backed enum, nor a `Decoder`, and there is no
  `using`;
- the default value of a decoded parameter cannot be exported (see Failure).

A decoder is a `Parameter` of its own, `Parameter\Decoded`, rather than a variation of
`ParameterAbstract`, which is about naming, typing and casting a value. `Decoded` holds the source,
the name, the decoder class-string, and the fallback (nullable, default), all exportable, with its
own `__set_state()`. `Path`, `Query` and `Part` are unchanged. `#[Body]` has no name to look up, so
it is only ever a `Decoded`.

### Failure

A decoder throws `Exception\NotDecodable` to reject a value. The parameter maps it by source, like the
scalar parameters do with `NotCastable`: `PathParameterNotValid` (404) for `#[Path]`, `ParameterNotValid` (400) for the
others. `InvalidArgumentException` and `ValueError` (thrown by `Enum::from()`) are also accepted, so
code such as `Slug::from()` needs no wrapping. Any other exception propagates as a bug.

A decoder is never called for a missing value, and never sees `null`: there is nothing it can do
with it. A value is missing when its key is absent or `null`, as for scalar parameters. The default
value applies, or `null` if the parameter is nullable, otherwise the request is not valid. A
`#[Body]` is never missing, an empty body is an empty file object and the decoder deals with it.

The default is stored in the plan, so it must be exportable with `var_export()`: `null`, a scalar,
an array, or an enum case. A default that is an object (`Page $page = new Page(1)`) fails the build,
with a message that says why; make the parameter nullable and apply the default in the action.

## Consequences

- Actions receive domain objects, and the mapping and its error handling live in one place.
- ADR 0001's rule that a class-typed parameter is rejected becomes: rejected unless it has a
  decoder.
- No serialization package is imposed. A decoder can wrap Valinor or the Symfony Serializer, but
  only through static calls, so a configured instance would need a static accessor, which is an
  awkward fit. That is the price of keeping the plan plain data.
- Decoders cannot be mocked through a container. They are tested by calling `decode()`.
- Type information is lost at the interface: `EncodedValue::$value` is `mixed`, and the
  documentation has to say what each source provides.

## Rejected names

`Resolver` clashes with `Parameter::resolve()`, `Hydrator` is too object-centric (`Slug` is not
hydrated), `Converter` and `Denormalizer` carry serializer baggage, and `Binder` is what the
attributes do.

## Rollout

1. `Decoder`, `EncodedValue` and `Exception\NotDecodable`.
2. `using` on `#[Path]`, `#[Query]` and `#[Part]`, validation in `PlanBuilder`, the `Decoded`
   parameter.
3. `#[Body]`.
4. Documentation, CHANGELOG.md, and the amendment of ADR 0001.
