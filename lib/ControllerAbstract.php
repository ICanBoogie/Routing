<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Controller\ActionEvent;
use ICanBoogie\Routing\Controller\BeforeActionEvent;

use function ICanBoogie\emit;

/**
 * A controller.
 *
 * **Note**: The controller cannot process multiple requests because it has a state.
 * It is recommended to use a fresh instance of a controller for each request.
 */
abstract class ControllerAbstract implements Responder
{
    /**
     * The current request.
     */
    // @phpstan-ignore-next-line
    public readonly Request $request;

    /**
     * The request's route.
     */
    // @phpstan-ignore-next-line
    public readonly Route $route;

    /**
     * {@link action()} can use this built-in response instead of returning its own instance.
     */
    // @phpstan-ignore-next-line
    protected readonly Response $response;

    /**
     * Responds to a request.
     *
     * A built-in response is available for {@link action()} to use, but if it returns a {@link Response}
     * instance it is returned as is. Any other type is used as the response's body. A `null` result does
     * not alter the body of the built-in response, the built-in response is returned as is.
     *
     * {@link Controller\BeforeActionEvent} is emitted before invoking {@link action()},
     * {@link Controller\ActionEvent} is emitted after.
     */
    final public function respond(Request $request): Response
    {
        // @phpstan-ignore-next-line
        $this->request = $request;
        // @phpstan-ignore-next-line
        $this->route = $request->context->get(Route::class);
        // @phpstan-ignore-next-line
        $this->response = new Response(headers: [

            'Content-Type' => 'text/html; charset=utf-8'

        ]);

        $result = null;

        emit(new BeforeActionEvent($this, $result));

        $result ??= $this->action($request);

        emit(new ActionEvent($this, $result));

        if ($result instanceof Response) {
            return $result;
        }

        if ($result !== null) {
            $this->response->body = $result;
        }

        return $this->response;
    }

    /**
     * @return Response|mixed
     */
    abstract protected function action(Request $request): mixed;
}
