<?php

namespace ICanBoogie\Routing\RouteProvider;

use ArrayIterator;
use ICanBoogie\Routing\Route;
use ICanBoogie\Routing\RouteProvider;
use Traversable;

use function array_values;
use function iterator_to_array;

/**
 * Speed up route resolution for predicate {@see ByUri}.
 */
final class MemoizeByUri implements RouteProvider
{
    public function __construct(
        private readonly RouteProvider $inner_provider
    ) {
    }

    public function route_for_predicate(callable $predicate): ?Route
    {
        if ($predicate instanceof ByUri) {
            return $this->route_for_uri($predicate);
        }

        return $this->inner_provider->route_for_predicate($predicate);
    }

    /**
     * @var Route[]
     */
    private array $routes;

    public function getIterator(): Traversable
    {
        return new ArrayIterator(
            $this->routes ??= array_values(iterator_to_array($this->inner_provider->getIterator()))
        );
    }

    private ?ByUriIndex $index = null;

    private function route_for_uri(ByUri $predicate): ?Route
    {
        if (!$this->index) {
            $routes = [ ...$this ];
            $this->index = new ByUriIndex(ByUriIndex::compile($routes), fn(int $i): Route => $routes[$i]);
        }

        return $this->index->find($predicate);
    }
}
