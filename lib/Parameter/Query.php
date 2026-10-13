<?php

namespace ICanBoogie\Routing\Parameter;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Exception\ParameterNotValid;
use ICanBoogie\Routing\ParameterAbstract;

/**
 * Provides a value of the query string, cast to the type declared by the action's parameter.
 *
 * The values are found in {@see Request::$query_params}. A value that is missing is the default
 * value, or `null` if the parameter is nullable, otherwise the request is not valid.
 */
final readonly class Query extends ParameterAbstract
{
    /**
     * @throws ParameterNotValid
     */
    public function resolve(Request $request): mixed
    {
        return $this->read('query', $request->query_params);
    }
}
