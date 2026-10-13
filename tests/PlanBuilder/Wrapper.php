<?php

namespace Test\ICanBoogie\Routing\PlanBuilder;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\IntoResponse;

final readonly class Wrapper implements IntoResponse
{
    public function into_response(Request $request): Response
    {
        return new Response();
    }

    public function again(): self
    {
        return $this;
    }
}
