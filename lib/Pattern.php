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

use ICanBoogie\Routing\Exception\InvalidPattern;

use function array_combine;
use function array_shift;
use function count;
use function is_array;
use function preg_match;
use function preg_quote;
use function preg_split;
use function str_contains;
use function strtr;
use function substr;
use function trim;
use function urlencode;

use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * Representation of a route pattern.
 *
 * <pre>
 * <?php
 *
 * use ICanBoogie\Routing\Pattern;
 *
 * $pattern = Pattern::from("/blog/<year:\d{4}>-<month:\d{2}>-:slug.html");
 * echo $pattern;       // "/blog/<year:\d{4}>-<month:\d{2}>-:slug.html"
 *
 * $pathname = $pattern->format([ 'year' => "2013", 'month' => "07", 'slug' => "test-is-a-test" ]);
 * echo $pathname;      // "/blog/2013-07-this-is-a-test.html"
 *
 * $matching = $pattern->matches($pathname, $captured);
 *
 * var_dump($matching); // true
 * var_dump($captured); // [ 'year' => "2013", 'month' => "07", 'slug' => "test-is-a-test" ]
 * </pre>
 */
final class Pattern
{
    private const EXTENDED_CHARACTER_CLASSES = [

        '{:uuid:}' => '[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89aAbB][a-f0-9]{3}-[a-f0-9]{12}',
        '{:sha1:}' => '[a-f0-9]{40}',

    ];

    /**
     * Parses a route pattern and returns an array of interleaved paths and parameters, the
     * parameter names and the regular expression for the specified pattern.
     *
     * @phpstan-return array{ 0: string, 1: string[]|string[], 2: array<int, int|string>, 3: string }
     */
    private static function parse(string $pattern): array
    {
        $catchall = false;

        if ($pattern[-1] == '*') {
            $catchall = true;
            $pattern = substr($pattern, 0, -1);
        }

        $pattern_extended = strtr($pattern, self::EXTENDED_CHARACTER_CLASSES);
        $parts = preg_split('#(:\w+|<(\w+:)?([^>]+)>)#', $pattern_extended, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            throw new InvalidPattern("Unable to parse pattern: $pattern.");
        }

        [ $interleaved, $params, $regex ] = self::parse_parts($parts);

        if ($catchall) {
            $regex .= '(.*)';
            $params[] = 'all';
        }

        $regex .= '$#';

        return [ $pattern, $interleaved, $params, $regex ]; // @phpstan-ignore-line
    }

    /**
     * Parses pattern parts.
     *
     * @param string[] $parts
     *
     * @return array{ 0: string|array{ 0: string, 1: string }, 1: string[], 2: string }
     */
    private static function parse_parts(array $parts): array
    {
        $regex = '#^';
        $interleaved = [];
        $params = [];
        $n = 0;

        for ($i = 0, $j = count($parts); $i < $j;) {
            $part = $parts[$i++];

            $regex .= preg_quote($part, '#');
            $interleaved[] = $part;

            if ($i == $j) {
                break;
            }

            $part = $parts[$i++];

            if ($part[0] == ':') {
                $identifier = substr($part, 1);
                $separator = $parts[$i];
                $selector = $separator ? '[^/\\' . $separator[0] . ']+' : '[^/]+';
            } else {
                $identifier = substr($parts[$i++], 0, -1);

                if (!$identifier) {
                    $identifier = $n++;
                }

                $selector = $parts[$i++];
            }

            $regex .= '(' . $selector . ')';
            $interleaved[] = [ $identifier, $selector ];
            $params[] = $identifier;
        }

        return [ $interleaved, $params, $regex ]; // @phpstan-ignore-line
    }

    /**
     * Reads an offset from an array.
     *
     * @param array<string, mixed> $container
     *
     * @return mixed
     */
    private static function read_value_from_array(array $container, string $key): mixed
    {
        return $container[$key];
    }

    /**
     * Reads a property from an object.
     */
    private static function read_value_from_object(object $container, string $key): mixed
    {
        return $container->$key;
    }

    /**
     * Checks if the given string is a route pattern.
     */
    public static function is_pattern(string $pattern): bool
    {
        return str_contains($pattern, '<') || str_contains($pattern, ':') || str_contains($pattern, '*');
    }

    /**
     * @var array<string, self>
     */
    private static array $instances = [];

    /**
     * Creates a {@link Pattern} instance from the specified pattern.
     */
    public static function from(string|self $pattern): self
    {
        if ($pattern instanceof self) {
            return $pattern;
        }

        if (!trim($pattern)) {
            throw new InvalidPattern("Pattern cannot be blank.");
        }

        return self::$instances[$pattern] ??= new self(...self::parse($pattern));
    }

    /**
     * @param array{
     *     'pattern': string,
     *     'interleaved': string[]|string[][],
     *     'params': array<int, int|string>,
     *     'regex': string
     *     } $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(
            $an_array['pattern'],
            $an_array['interleaved'],
            $an_array['params'],
            $an_array['regex']
        );
    }

    /*
     * INSTANCE
     */

    /**
     * @param string $pattern
     * @param string[]|string[][] $interleaved Interleaved pattern.
     * @param array<int, int|string> $params Params of the pattern.
     * @param string $regex Regex of the pattern.
     */
    private function __construct(
        public readonly string $pattern,
        public readonly array $interleaved,
        public readonly array $params,
        public readonly string $regex
    ) {
    }

    /**
     * Returns the route pattern specified during construct.
     */
    public function __toString(): string
    {
        return $this->pattern;
    }

    /**
     * Formats a pattern with the specified values.
     *
     * @param array<string|int, mixed>|object|null $values The values to format the pattern, either as an array or an
     * object. If value is an instance of {@link ToSlug} the `to_slug()` method is used to
     * transform the instance into a URL component.
     *
     * @throws PatternRequiresValues in attempt to format a pattern requiring values without
     * providing any.
     */
    public function format(array|object|null $values = null): string
    {
        if (!$this->params) {
            return $this->pattern;
        }

        if (!$values) {
            throw new PatternRequiresValues($this);
        }

        return $this->format_parts($values);
    }

    /**
     * Formats pattern parts.
     *
     * @param array<int|string, mixed>|object $container
     *
     * @uses read_value_from_array
     * @uses read_value_from_object
     */
    private function format_parts(array|object $container): string
    {
        $url = '';
        $method = 'read_value_from_' . (is_array($container) ? 'array' : 'object');

        foreach ($this->interleaved as $i => $value) {
            $url .= $i % 2 ? $this->format_part(self::$method($container, $value[0])) : $value;
        }

        return $url;
    }

    /**
     * Formats pattern part.
     */
    private function format_part(string|ToSlug $value): string
    {
        if ($value instanceof ToSlug) {
            $value = $value->to_slug();
        }

        return urlencode($value);
    }

    /**
     * Checks if a pathname matches the pattern.
     *
     * @param array<string, string> $captured The parameters captured from the pathname.
     */
    public function matches(string $pathname, array &$captured = null): bool
    {
        $captured = [];

        #
        # `params` is empty if the pattern is a plain string, thus we can simply compare strings.
        #

        if (!$this->params) {
            return $pathname === $this->pattern;
        }

        if (!preg_match($this->regex, $pathname, $matches)) {
            return false;
        }

        array_shift($matches);

        $captured = array_combine($this->params, $matches);

        return true;
    }
}
