<?php

namespace ICanBoogie\Routing\Parameter;

use BackedEnum;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\Parameter;
use LogicException;

use function array_key_exists;
use function get_debug_type;
use function is_string;

/**
 * Provides a path parameter, cast to the type declared by the action's parameter.
 *
 * The path values are the strings captured by the pattern of the route, found in
 * {@see Request::$path_params}.
 */
final readonly class Path implements Parameter
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
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array);
    }

    /**
     * @param non-empty-string $name
     *     Name of the placeholder in the pattern, which is the name of the parameter.
     * @param 'int'|'float'|'string'|'bool'|class-string<BackedEnum> $type
     * @param 'int'|'string'|null $backing
     *     The type backing the enum, when `$type` is an enum.
     * @param bool $nullable
     *     Whether the parameter accepts `null`, which is the value when the placeholder is not
     *     part of the matched route and there is no default.
     * @param bool $has_default
     *     Whether the parameter has a default value, which is the value when the placeholder is
     *     not part of the matched route.
     * @param mixed $default
     *     The default value, it must be exportable with `var_export()`.
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?string $backing = null,
        public bool $nullable = false,
        public bool $has_default = false,
        public mixed $default = null,
    ) {
    }

    /**
     * @throws PathParameterNotValid if the value cannot be cast to the type of the parameter.
     */
    public function resolve(Request $request): mixed
    {
        $values = $request->path_params;

        if (!array_key_exists($this->name, $values)) {
            return $this->resolve_absent();
        }

        $value = $values[$this->name];

        try {
            return is_string($value)
                ? Casting::cast($this->type, $this->backing, $value)
                : throw new NotCastable("Not a string: " . get_debug_type($value));
        } catch (NotCastable $e) {
            throw new PathParameterNotValid($this->name, $this->type, $e);
        }
    }

    /**
     * A parameter that is neither nullable nor has a default must be part of every route of the
     * action, which the configuration ensures. Reaching the exception is a wiring mistake.
     */
    private function resolve_absent(): mixed
    {
        return match (true) {
            $this->has_default => $this->default,
            $this->nullable => null,
            default => throw new LogicException("The path parameter '$this->name' is not available."),
        };
    }
}
