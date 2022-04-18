<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use InvalidArgumentException;

use function in_array;
use function is_array;

final class Route
{
    /**
     * Separator used for actions e.g. "articles:show".
     */
    public const ACTION_SEPARATOR = ':';

    public readonly Pattern $pattern;

    /**
     * @param string $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action. e.g. 'articles:show'.
     * @param RequestMethod|RequestMethod[] $methods Request method(s) accepted by the respond.
     * @param object[] $extensions
     */
    public function __construct(
        string $pattern,
        public readonly string $action,
        public readonly RequestMethod|array $methods = RequestMethod::METHOD_ANY,
        public readonly string|null $id = null,
        public readonly array $extensions = [],
    ) {
        if (!$action) {
            throw new InvalidArgumentException("The action cannot be blank.");
        }

        $this->pattern = Pattern::from($pattern);
    }

    /**
     * Whether the specified method matches with the method(s) supported by the route.
     */
    public function method_matches(RequestMethod $method): bool
    {
        $methods = $this->methods;

        if ($method === RequestMethod::METHOD_ANY || $method === $methods || $methods === RequestMethod::METHOD_ANY) {
            return true;
        }

        return is_array($methods) && in_array($method, $methods);
    }
}
