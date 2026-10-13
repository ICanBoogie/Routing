<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\PathParameterNotValid;
use ICanBoogie\Routing\ParameterAbstract;
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
final readonly class Path extends ParameterAbstract
{
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
