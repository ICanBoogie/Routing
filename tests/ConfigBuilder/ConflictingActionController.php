<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class ConflictingActionController
{
    #[Get('/conflict', action: 'photos:show')]
    public function show(): Response
    {
        return new Response();
    }
}
