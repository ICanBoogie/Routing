<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

abstract class AbstractController
{
    #[Get('/')]
    public function index(): Response
    {
        return new Response();
    }
}
