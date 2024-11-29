<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\RequestMethod;
use InvalidArgumentException;

use function in_array;
use function is_array;

final class Route
{
    /**
     * Separator used for actions; for example, "articles:show".
     */
    public const string ACTION_SEPARATOR = ':';

    /**
     * @param array{
     *     'pattern': Pattern,
     *     'action': string,
     *     'methods': RequestMethod|RequestMethod[],
     *     'id': string,
     *     'extensions': object[]
     * } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(
            $an_array['pattern'],
            $an_array['action'],
            $an_array['methods'],
            $an_array['id'],
            $an_array['extensions']
        );
    }

    public readonly Pattern $pattern;

    /**
     * @param string|Pattern $pattern Pattern of the route.
     * @param string $action Identifier of a qualified action; for example, 'articles:show'.
     * @param RequestMethod|RequestMethod[] $methods Request methods accepted by the route.
     * @param object[] $extensions
     */
    public function __construct(
        string|Pattern $pattern,
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
     * Whether the specified method matches with the methods supported by the route.
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
