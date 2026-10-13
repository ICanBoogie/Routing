<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class IdIsActionController
{
    #[Get('/a', id: 'id-action:b')]
    public function a(): Response
    {
        return new Response();
    }

    #[Get('/b', action: 'id-action:b')]
    public function b(): Response
    {
        return new Response();
    }
}
