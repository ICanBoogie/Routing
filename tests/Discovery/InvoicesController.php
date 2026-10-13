<?php

namespace Test\ICanBoogie\Routing\Discovery;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Post;

final class InvoicesController
{
    #[Get('/invoices')]
    public function list(): Response
    {
        return new Response();
    }

    #[Post('/invoices')]
    public function create(): Response
    {
        return new Response();
    }
}
