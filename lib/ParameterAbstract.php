<?php

namespace ICanBoogie\Routing;

use BackedEnum;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Parameter\Casting;
use ICanBoogie\Routing\Parameter\NotCastable;

/**
 * The base of the parameters that provide a value of the request, found by name and cast to the
 * type declared by the action's parameter, e.g. {@see Parameter\Path}, {@see Parameter\Query}, and
 * {@see Parameter\Part}.
 *
 * Parameters that don't depend on a name or a type, such as {@see Parameter\CurrentRequest}, don't
 * extend it.
 */
abstract readonly class ParameterAbstract implements Parameter
{
    /**
     * @param array{
     *     name: non-empty-string,
     *     type: 'int'|'float'|'string'|'bool'|class-string<BackedEnum>,
     *     backing: 'int'|'string'|null,
     *     nullable: bool,
     *     has_default: bool,
     *     default: mixed,
     * } $an_array
     */
    final public static function __set_state(array $an_array): static
    {
        return new static(...$an_array);
    }

    /**
     * @param non-empty-string $name
     *     Name of the value in its source.
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     *     The type backing the enum, when `$type` is an enum.
     * @param bool $nullable
     *     Whether the parameter accepts `null`, which is the value when the value is missing and
     *     there is no default.
     * @param bool $has_default
     *     Whether the parameter has a default value, which is the value when the value is missing.
     * @param mixed $default
     *     The default value, it must be exportable with `var_export()`.
     */
    final public function __construct(
        public string $name,
        public string $type,
        public ?string $backing = null,
        public bool $nullable = false,
        public bool $has_default = false,
        public mixed $default = null,
    ) {
    }

    /**
     * Reads the value from the values provided by the client, the query string or the body.
     *
     * A value that is missing, or `null`, is the default value, or `null` if the parameter is
     * nullable, otherwise the request is not valid.
     *
     * @param 'query'|'part' $source
     * @param array<string, mixed> $values
     *
     * @throws ParameterNotValid if the value is missing and there is no fallback, or if it cannot
     *     be cast to the type.
     */
    protected function read(string $source, array $values): mixed
    {
        $value = $values[$this->name] ?? null;

        if ($value === null) {
            return match (true) {
                $this->has_default => $this->default,
                $this->nullable => null,
                default => throw new ParameterNotValid($source, $this->name, $this->type, missing: true),
            };
        }

        try {
            return Casting::cast_input($this->type, $this->backing, $value);
        } catch (NotCastable $e) {
            throw new ParameterNotValid($source, $this->name, $this->type, previous: $e);
        }
    }
}
