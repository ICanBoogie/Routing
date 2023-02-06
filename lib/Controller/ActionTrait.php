<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\Controller;

use ICanBoogie\HTTP\Request;
use ICanBoogie\Routing\Route;
use LogicException;

use function array_values;
use function implode;
use function method_exists;
use function preg_replace;
use function strpos;
use function strtolower;
use function substr;

/**
 * Action controller implementation.
 */
trait ActionTrait
{
    protected function action(Request $request): mixed
    {
        return $this->resolve_action($request)();
    }

    /**
     * Resolves the action into a callable.
     */
    private function resolve_action(Request $request): callable
    {
        $method = $this->resolve_action_method($request);
        $args = $this->resolve_action_args($request);

        return fn() => $this->$method(...$args);
    }

    private function resolve_action_method(Request $request): string
    {
        $action = $request->context->get(Route::class)->action;
        $methods = [];

        for (;;) {
            $base = preg_replace('/[^A-Za-z]+/', '_', $action);
            $methods[] = strtolower($request->method->value) . '_' . $base;
            $methods[] = 'any_' . $base;
            $methods[] = $base;

            $pos = strpos($action, Route::ACTION_SEPARATOR);

            if ($pos === false) {
                break;
            }

            $action = substr($action, $pos + 1);
        }

        foreach ($methods as $method) {
            if (method_exists($this, $method)) {
                return $method;
            }
        }

        throw new LogicException("Unable to find action method, tried: " . implode(', ', $methods));
    }

    /**
     * @return string[]
     */
    private function resolve_action_args(Request $request): array
    {
        return array_values($request->path_params);
    }
}
