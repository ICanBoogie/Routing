<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class SameIdController
{
    #[Get('/a', id: 'same')]
    public function a(): Response
    {
        return new Response();
    }

    #[Get('/b', id: 'same')]
    public function b(): Response
    {
        return new Response();
    }
}
