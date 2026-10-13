<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder\Cart;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Prefix;

#[Prefix(route: '/cart', action: 'cart')]
final class PhotosController
{
    #[Get('/photos/:id')]
    public function show(int $id): Response
    {
        return new Response();
    }

    #[Get('/explicit', action: 'custom')]
    public function explicit(): Response
    {
        return new Response();
    }
}
