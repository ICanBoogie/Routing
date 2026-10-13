<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;

final class PlainController
{
    public function list(): Response
    {
        return new Response();
    }

    public function show(int $id): Response
    {
        return new Response();
    }

    public function create(): Response
    {
        return new Response();
    }
}
