<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Post;

final class PhotosController
{
    #[Get('/photos')]
    public function list(): Response
    {
        return new Response();
    }

    #[Get('/photos/:id')]
    public function show(int $id): Response
    {
        return new Response();
    }

    #[Post('/photos')]
    public function create(): Response
    {
        return new Response();
    }
}
