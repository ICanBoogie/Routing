<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class ArticlesController
{
    #[Get('/articles')]
    #[Get('/articles/page/:page', id: 'articles-page')]
    public function index(?int $page = null): Response
    {
        return new Response();
    }

    #[Get('/old', action: 'articles:old')]
    #[Get('/older', action: 'articles:older')]
    public function legacy(): Response
    {
        return new Response();
    }
}
