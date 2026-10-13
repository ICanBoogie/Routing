<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use ICanBoogie\Routing\Route as RouteDefinition;
use InvalidArgumentException;

use function str_contains;

/**
 * Defines the name of a controller, which replaces the name inferred from its class in the actions
 * of its routes.
 *
 * The name is used as is, e.g. `#[Name('contact')]` turns the action `contacts:show` into
 * `contact:show`, whatever the name of the class is.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Name
{
    /**
     * @param non-empty-string $value
     *     The name of the controller. It cannot contain the separator `:`.
     *
     * @throws InvalidArgumentException if the name is blank or contains the separator.
     */
    public function __construct(
        public string $value,
    ) {
        // @phpstan-ignore-next-line // double check
        if ($value === '') {
            throw new InvalidArgumentException("The name cannot be blank.");
        }

        if (str_contains($value, RouteDefinition::ACTION_SEPARATOR)) {
            throw new InvalidArgumentException("The name `$value` cannot contain `" . RouteDefinition::ACTION_SEPARATOR . "`.");
        }
    }
}
