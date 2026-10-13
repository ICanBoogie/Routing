<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class PrivateRouteController
{
    #[Get('/x')]
    private function hidden(): Response
    {
        return new Response();
    }
}
