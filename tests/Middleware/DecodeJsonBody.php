<?php

namespace Test\ICanBoogie\Routing\Middleware;

use ICanBoogie\HTTP\ClientError;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Middleware;
use JsonException;

/**
 * The middleware of "Reading a JSON body" in docs/StatelessControllers.md, keep them in sync.
 */
final class DecodeJsonBody implements Middleware
{
    public function responder(Responder $next): Responder
    {
        return new class ($next) implements Responder {
            public function __construct(private Responder $next)
            {
            }

            public function respond(Request $request): Response
            {
                if ($request->headers->content_type->type === 'application/json' && $request->body_stream) {
                    $request->body_stream->rewind();

                    $json = '';

                    while (!$request->body_stream->eof()) {
                        $json .= $request->body_stream->fread(8192);
                    }

                    try {
                        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        throw new ClientError("The body is not valid JSON.", previous: $e);
                    }

                    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
                        throw new ClientError("Expected a JSON object.");
                    }

                    /** @var array<string, mixed> $data */
                    $request->request_params = $data;
                }

                return $this->next->respond($request);
            }
        };
    }
}
