<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;
use ICanBoogie\Routing\Attribute\Part;
use ICanBoogie\Routing\Attribute\Path;
use ICanBoogie\Routing\Attribute\Post;
use ICanBoogie\Routing\Attribute\Query;

final class SearchController
{
    #[Get('/search/:scope')]
    public function search(
        #[Path] string $scope,
        #[Query('q')] string $term,
        #[Query] int $page = 1
    ): Response {
        return new Response("$scope:$term:$page");
    }

    #[Post('/search/:scope')]
    public function save(#[Path] string $scope, #[Part] string $title, #[Part] ?bool $public): Response
    {
        return new Response($scope . ':' . $title . ':' . var_export($public, true));
    }
}
