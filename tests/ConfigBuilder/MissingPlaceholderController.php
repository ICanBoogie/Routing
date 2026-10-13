<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class MissingPlaceholderController
{
    #[Get('/photos/:slug')]
    public function show(int $id): Response
    {
        return new Response();
    }
}
