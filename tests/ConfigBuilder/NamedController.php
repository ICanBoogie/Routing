<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Name;
use ICanBoogie\Routing\Attribute\Prefix;

#[Name('contact')]
#[Prefix(action: 'site')]
final class NamedController
{
    #[Get('/contact')]
    public function show(): Response
    {
        return new Response();
    }
}
