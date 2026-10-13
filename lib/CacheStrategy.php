<?php

namespace ICanBoogie\Routing;

/**
 * How the routes of a {@see Config} are served, see {@see ConfigBuilder::cache_strategy()}.
 */
enum CacheStrategy
{
    /**
     * Routes are tested one after the other. Nothing is computed ahead. This is the default, the
     * resulting {@see Config} can be exported with `var_export()`.
     */
    case NONE;

    /**
     * Routes are indexed in memory by {@see RouteProvider\Memoize}, on first use. It is meant for
     * development, where the configuration is built on each request: the resulting {@see Config}
     * can't be exported with `var_export()`, because the indexes are not exportable.
     */
    case MEMOIZE;

    /**
     * Routes are compiled by {@see RouteProvider\Compiled}. It is meant for production, the
     * resulting {@see Config} can be exported with `var_export()`. Use it in production.
     */
    case COMPILE;
}
