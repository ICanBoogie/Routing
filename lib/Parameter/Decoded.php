<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Decoder;
use ICanBoogie\Routing\EncodedValue;
use ICanBoogie\Routing\Exception\NotDecodable;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\Parameter;
use InvalidArgumentException;
use LogicException;
use ValueError;

/**
 * Provides an object, made by a {@see Decoder} from a value of the path, the query string, or the
 * body, or from the raw body, the `SplFileObject` of {@see Request::$body_stream}.
 *
 * The decoder is never called for a missing value, a key that is absent or `null`: the value is the
 * default value, or `null` if the parameter is nullable, otherwise the request is not valid. The
 * raw body is never missing, an empty body is an empty file.
 *
 * A decoder rejects a value with {@see NotDecodable}, `InvalidArgumentException`, or `ValueError`,
 * which are reported like a value that cannot be cast: a "not found" for a path value, a "bad
 * request" for the other sources.
 */
final readonly class Decoded implements Parameter
{
    /**
     * @param array{
     *     source: 'path'|'query'|'part'|'body',
     *     name: non-empty-string,
     *     decoder: class-string<Decoder>,
     *     into_class: string,
     *     nullable: bool,
     *     has_default: bool,
     *     default: mixed,
     * } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param 'path'|'query'|'part'|'body' $source
     *     Where the value is read from.
     * @param non-empty-string $name
     *     Name of the value in its source, the name of the parameter for the body.
     * @param class-string<Decoder> $decoder
     * @param string $into_class
     *     Type of the parameter, without its nullability. It is given to the decoder, and used to
     *     report invalid values.
     * @param bool $nullable
     *     Whether the parameter accepts `null`, which is the value when the value is missing and
     *     there is no default.
     * @param bool $has_default
     *     Whether the parameter has a default value, which is the value when the value is missing.
     * @param mixed $default
     *     The default value, it must be exportable with `var_export()`.
     */
    public function __construct(
        public string $source,
        public string $name,
        public string $decoder,
        public string $into_class,
        public bool $nullable = false,
        public bool $has_default = false,
        public mixed $default = null,
    ) {
    }

    /**
     * @throws PathParameterNotValid if the value of a path cannot be decoded.
     * @throws ParameterNotValid if the value is missing, or if it cannot be decoded.
     */
    public function resolve(Request $request): mixed
    {
        $value = match ($this->source) {
            'path' => $request->path_params[$this->name] ?? null,
            'query' => $request->query_params[$this->name] ?? null,
            'part' => $request->request_params[$this->name] ?? null,
            'body' => Body::of($request),
        };

        if ($value === null) {
            return $this->resolve_missing();
        }

        try {
            return $this->decoder::decode(new EncodedValue(
                $value,
                $this->source,
                $this->into_class,
                $this->source === 'body' ? null : $this->name,
            ));
        } catch (NotDecodable | InvalidArgumentException | ValueError $e) {
            throw $this->source === 'path'
                ? new PathParameterNotValid($this->name, $this->into_class, $e)
                : new ParameterNotValid($this->source, $this->name, $this->into_class, previous: $e);
        }
    }

    /**
     * A path parameter that is neither nullable nor has a default must be part of every route of
     * the action, which the configuration ensures. Reaching the exception is a wiring mistake.
     */
    private function resolve_missing(): mixed
    {
        return match (true) {
            $this->has_default => $this->default,
            $this->nullable => null,
            $this->source === 'path' => throw new LogicException("The path parameter '$this->name' is not available."),
            default => throw new ParameterNotValid($this->source, $this->name, $this->into_class, missing: true),
        };
    }
}
