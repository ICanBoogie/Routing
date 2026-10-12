<?php

/**
 * Placeholders referenced by the documentation examples, they are only seen by PHPStan.
 */

namespace {
    use ICanBoogie\HTTP\Request;
    use ICanBoogie\HTTP\Responder;
    use ICanBoogie\HTTP\Response;

    class ListArticles implements Responder
    {
        public function respond(Request $request): Response
        {
            return new Response();
        }
    }

    class ShowArticle extends ListArticles
    {
    }

    function fetch_from_cache(Request $request): ?Response
    {
        return null;
    }
}
