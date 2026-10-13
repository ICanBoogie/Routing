<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class DuplicateActionController
{
    #[Get('/a', action: 'dup:action')]
    public function a(): Response
    {
        return new Response();
    }

    #[Get('/b', action: 'dup:action')]
    public function b(): Response
    {
        return new Response();
    }
}
