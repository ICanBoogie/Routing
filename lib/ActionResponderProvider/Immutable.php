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

use ICanBoogie\HTTP\Responder;
use ICanBoogie\Routing\ActionResponderProvider;
use IteratorAggregate;
use Traversable;

/**
 * Provides action responders from an immutable list.
 *
 * @implements IteratorAggregate<string, Responder>
 *     Where _key_ is an Action and _value_ a Responder.
 */
class Immutable implements ActionResponderProvider, IteratorAggregate
{
    private readonly Mutable $mutable;

    /**
     * @param array<string, Responder> $responders
     *     Where _key_ is an Action and _value_ a Responder.
     */
    public function __construct(array $responders = [])
    {
        $this->mutable = new Mutable();

        foreach ($responders as $action => $responder) {
            $this->mutable->add_responder($action, $responder);
        }
    }

    public function responder_for_action(string $action): ?Responder
    {
        return $this->mutable->responder_for_action($action);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->mutable as $action => $responder) {
            yield $action => $responder;
        }
    }
}
