<?php

namespace ICanBoogie\Routing;

/**
 * A route provider that supports mutations.
 */
interface MutableRouteProvider extends RouteProvider
{
    /**
     * Add routes to the provider.
     */
    public function add_routes(Route ...$route): void;
}
