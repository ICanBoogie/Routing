<?php

namespace ICanBoogie\Routing\Attribute;

/**
 * The type of the elements of an `array` parameter bound with {@see Query} or {@see Part}, given
 * with their `of` argument. A backed enum is given by its class name instead.
 *
 * PHP can't declare the type of the elements in a signature, this is where it is declared.
 */
enum ElementType
{
    /**
     * Elements are strings, a number or a boolean (JSON) is not valid.
     */
    case String;
    case Int;
    case Float;
    case Bool;

    /**
     * The array is provided as the client sent it: elements are neither cast nor validated, and
     * they can be arrays themselves, e.g. `items[0][sku]=x`.
     */
    case Mixed;
}
