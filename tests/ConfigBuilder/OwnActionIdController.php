<?php

namespace Test\ICanBoogie\Routing\ConfigBuilder;

use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Attribute\Get;

final class OwnActionIdController
{
    #[Get('/own', action: 'own:action', id: 'own:action')]
    public function show(): Response
    {
        return new Response();
    }
}
