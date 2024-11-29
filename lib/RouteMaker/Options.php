<?php

namespace ICanBoogie\Routing\RouteMaker;

final class Options
{
    public const string DEFAULT_ID_NAME = 'id';
    public const string DEFAULT_ID_REGEX = '\d+';

    /**
     * @param string $id_name Name of the identifier property.
     * @param string $id_regex Regex of the identifier value.
     * @param string[] $only Only the actions specified are included.
     * @param string[] $except The actions specified are excluded.
     * @param array<string, string> $as
     *   Overload names of actions. _key_ is an action, _value_ a name.
     * @param array<string, string> $ids
     *   Identifiers for the routes. _key_ is an action, _value_ an identifier.
     * @param array<string, Basics> $basics Overload actions pattern and methods.
     */
    public function __construct(
        public string $id_name = self::DEFAULT_ID_NAME,
        public string $id_regex = self::DEFAULT_ID_REGEX,
        public array $only = [],
        public array $except = [],
        public array $as = [],
        public array $ids = [],
        public array $basics = [],
    ) {
    }
}
