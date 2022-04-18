<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing\ActionResponderProvider;

use ArrayIterator;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\MutableActionResponderProvider;
use IteratorAggregate;
use Traversable;

/**
 * Provides action responders from a mutable list.
 *
 * @implements IteratorAggregate<string, Responder>
 *     Where _key_ is an Action and _value_ a Responder.
 */
final class Mutable implements MutableActionResponderProvider, IteratorAggregate
{
    /**
     * @var array<string, Responder>
     *     Where _key_ is an Action and _value_ a Responder.
     */
    private array $responders = [];

    public function responder_for_action(string $action): ?Responder
    {
        return $this->responders[$action] ?? null;
    }

    public function add_responder(string $action, Responder $responder): void
    {
        $this->responders[$action] = $responder;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->responders);
    }
}
