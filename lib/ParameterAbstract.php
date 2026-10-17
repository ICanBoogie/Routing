<?php

namespace ICanBoogie\Routing;

use BackedEnum;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\Parameter\Casting;
use ICanBoogie\Routing\Parameter\NotCastable;

use function array_map;
use function is_array;

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
     *     type: 'int'|'float'|'string'|'bool'|'mixed'|class-string<BackedEnum>,
     *     backing: 'int'|'string'|null,
     *     nullable: bool,
     *     has_default: bool,
     *     default: mixed,
     *     array: bool,
     * } $an_array
     */
    final public static function __set_state(array $an_array): static
    {
        return new static(...$an_array);
    }

    /**
     * @param non-empty-string $name
     *     Name of the value in its source.
     * @param 'int'|'float'|'string'|'bool'|'mixed'|class-string<BackedEnum> $type
     *     Type of the value, or of its elements if `$array`. `mixed` is only valid for an array,
     *     its elements are not cast.
     * @param 'int'|'string'|null $backing
     *     The type backing the enum, when `$type` is an enum.
     * @param bool $nullable
     *     Whether the parameter accepts `null`, which is the value when the value is missing and
     *     there is no default.
     * @param bool $has_default
     *     Whether the parameter has a default value, which is the value when the value is missing.
     * @param mixed $default
     *     The default value, it must be exportable with `var_export()`.
     * @param bool $array
     *     Whether the value is an array of `$type`.
     */
    final public function __construct(
        public string $name,
        public string $type,
        public ?string $backing = null,
        public bool $nullable = false,
        public bool $has_default = false,
        public mixed $default = null,
        public bool $array = false,
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

        if ($this->array) {
            return $this->read_array($source, $value);
        }

        try {
            return Casting::cast_input($this->type, $this->backing, $value);
        } catch (NotCastable $e) {
            throw new ParameterNotValid($source, $this->name, $this->type, previous: $e);
        }
    }

    /**
     * Keys are preserved, `tags[]` makes a list, `tags[a]` a map. A `mixed` array is returned as is.
     *
     * @param 'query'|'part' $source
     *
     * @return array<int|string, mixed>
     *
     * @throws ParameterNotValid if the value is not an array, or if an element cannot be cast.
     */
    private function read_array(string $source, mixed $value): array
    {
        $type = $this->type === 'mixed' ? 'array' : "array<$this->type>";

        if (!is_array($value)) {
            throw new ParameterNotValid($source, $this->name, $type);
        }

        try {
            return array_map(fn(mixed $element) => Casting::cast_input($this->type, $this->backing, $element), $value);
        } catch (NotCastable $e) {
            throw new ParameterNotValid($source, $this->name, $type, previous: $e);
        }
    }
}
