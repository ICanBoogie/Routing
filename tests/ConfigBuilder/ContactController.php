<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class ContactController
{
    #[Get('/contact')]
    public function show(): Response
    {
        return new Response();
    }
}
