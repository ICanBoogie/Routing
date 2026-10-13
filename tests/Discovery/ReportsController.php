<?php

namespace Test\ICanBoogie\Routing\Discovery;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Prefix;

#[Prefix(pattern: '/reports', action: 'admin')]
final class ReportsController
{
    #[Get('/:id')]
    public function show(int $id): Response
    {
        return new Response();
    }

    public function not_an_action(): void
    {
    }
}
