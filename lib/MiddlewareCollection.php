<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Responder;

use function array_merge;

/**
 * A collection of middleware.
 */
final class MiddlewareCollection
{
    /**
     * @var array<Middleware>
     */
    private array $middleware = [];

    /**
     * @param iterable<Middleware> $middleware
     */
    public function __construct(iterable $middleware = [])
    {
        $this->add(...$middleware);
    }

    public function add(Middleware ...$middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);
    }

    public function chain(Responder $endpoint): Responder
    {
        $chain = $endpoint;

        foreach ($this->middleware as $m) {
            $chain = $m->responder($chain);
        }

        return $chain;
    }
}
