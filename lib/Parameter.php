<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;

/**
 * A parameter of an action method.
 *
 * A parameter is part of the plan of a method, which is computed once when the configuration is
 * built. It is plain data that can be exported with `var_export()`, and it knows how to produce
 * its value from the request.
 */
interface Parameter
{
    /**
     * Produces the value of the parameter.
     *
     * @throws Exception when the value provided by the client is not valid.
     */
    public function resolve(Request $request): mixed;
}
