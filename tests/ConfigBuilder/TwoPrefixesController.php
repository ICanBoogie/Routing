<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Prefix;

#[Prefix(route: '/a')]
#[Prefix(action: 'b')]
final class TwoPrefixesController
{
    #[Get('/')]
    public function index(): Response
    {
        return new Response();
    }
}
