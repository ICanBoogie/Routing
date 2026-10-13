<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;

final class NoRoutesController
{
    public function index(): Response
    {
        return new Response();
    }
}
