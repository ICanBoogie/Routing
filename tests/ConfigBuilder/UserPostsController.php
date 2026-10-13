<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Prefix;

#[Prefix(pattern: '/users/:user_id')]
final class UserPostsController
{
    #[Get('/posts')]
    public function list(int $user_id): Response
    {
        return new Response();
    }
}
