<?php

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * An action may return an instance of this interface instead of a response, the instance knows how
 * to turn itself into one.
 *
 * It allows wrappers, such as a view or a JSON payload, to be returned by actions that don't
 * need to know about HTTP.
 */
interface IntoResponse
{
    public function into_response(Request $request): Response;
}
