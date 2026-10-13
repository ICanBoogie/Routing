<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class FirstIdController
{
    #[Get('/first', id: 'first-route')]
    public function show(): Response
    {
        return new Response();
    }
}
