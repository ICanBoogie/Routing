<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use LogicException;

final class FakeResponder implements Responder
{
    public function respond(Request $request): Response
    {
        throw new LogicException();
    }
}
