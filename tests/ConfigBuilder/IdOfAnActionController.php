<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class IdOfAnActionController
{
    #[Get('/x', id: 'first-ids:show')]
    public function show(): Response
    {
        return new Response();
    }
}
