<?php

namespace ICanBoogie\Routing\Attribute;

use Attribute;
use InvalidArgumentException;

/**
 * Defines the prefixes shared by the methods of a controller.
 *
 * Both prefixes are concatenated before the pattern and the action of each route of the
 * controller, e.g. `#[Prefix(route: '/cart', action: 'cart')]` turns the route `/photos/:id` with
 * the action `photos:show` into `/cart/photos/:id` and `cart:photos:show`.
 *
 * The class is not final, an application can extend it to avoid repeating the same values on
 * multiple controllers.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Prefix
{
    /**
     * @param non-empty-string|null $route
     *     Prefix of the pattern of the routes. It can contain placeholders, which become path
     *     parameters of every method of the controller.
     * @param non-empty-string|null $action
     *     Prefix of the action of the routes. The separator `:` is added automatically.
     *
     * @throws InvalidArgumentException if both are omitted, or if one is blank.
     */
    public function __construct(
        public ?string $route = null,
        public ?string $action = null,
    ) {
        if ($route === null && $action === null) {
            throw new InvalidArgumentException("At least one of `route` or `action` is required.");
        }

        // @phpstan-ignore-next-line // double check
        if ($route === '' || $action === '') {
            throw new InvalidArgumentException("The `route` and the `action` cannot be blank.");
        }
    }
}
