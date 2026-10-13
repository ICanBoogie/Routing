<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Routing\Exception\InvalidPattern;
use InvalidArgumentException;
use Stringable;
use WeakReference;

use function array_combine;
use function array_map;
use function array_shift;
use function count;
use function explode;
use function get_debug_type;
use function implode;
use function is_array;
use function is_scalar;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function property_exists;
use function rawurldecode;
use function rawurlencode;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strtr;
use function substr;
use function trim;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;

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
    private const array EXTENDED_CHARACTER_CLASSES = [

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
        $catchall = $pattern[-1] === '*';
        $path = $catchall ? substr($pattern, 0, -1) : $pattern;

        [ $interleaved, $params, $regex ] = self::parse_parts($pattern, strtr($path, self::EXTENDED_CHARACTER_CLASSES));

        if ($catchall) {
            $regex .= '(.*)';
            $params[] = 'all';
            $interleaved[] = [ 'all', '.*' ];
            $interleaved[] = '';
        }

        self::assert_unique_params($pattern, $params);

        $regex .= '$#';

        return [ $pattern, $interleaved, $params, $regex ]; // @phpstan-ignore-line
    }

    /**
     * @param array<int, int|string> $params
     *
     * @throws InvalidPattern
     */
    private static function assert_unique_params(string $pattern, array $params): void
    {
        $seen = [];

        foreach ($params as $name) {
            if (isset($seen[$name])) {
                throw new InvalidPattern("Parameter '$name' is defined twice in pattern: $pattern.");
            }

            $seen[$name] = true;
        }
    }

    /**
     * Splits a pattern into literals and parameters, and builds the regex while doing so.
     *
     * @return array{ 0: array<int, string|array{ 0: int|string, 1: string }>, 1: array<int, int|string>, 2: string }
     *     The interleaved literals and parameters (literals at even indexes), the parameter names,
     *     and the regex without its end.
     */
    private static function parse_parts(string $pattern, string $extended): array
    {
        $found = preg_match_all(
            '#:(?<name>\w+)|<(?:(?<id>\w+):)?(?<constraint>[^>]+)>#',
            $extended,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL
        );

        if ($found === false) {
            throw new InvalidPattern("Unable to parse pattern: $pattern.");
        }

        $regex = '#^';
        $interleaved = [];
        $params = [];
        $anonymous = 0;
        $offset = 0;

        foreach ($matches as $match) {
            [ $token, $start ] = $match[0];

            $literal = substr($extended, $offset, $start - $offset);
            $offset = $start + strlen((string) $token);

            if ($match['name'][0] !== null) {
                // The selector excludes the character that follows, so that `:a.:b` and `:a-:b` split.
                $identifier = (string) $match['name'][0];
                $following = $extended[$offset] ?? '';
                $selector = $following !== '' && !self::starts_token($extended, $offset)
                    ? '[^/\\' . $following . ']+'
                    : '[^/]+';
            } else {
                $identifier = ($match['id'][0] ?? '') !== '' ? (string) $match['id'][0] : $anonymous++;
                $selector = (string) $match['constraint'][0];

                self::assert_no_capturing_group($pattern, (string) $identifier, $selector);
            }

            $regex .= preg_quote($literal, '#') . '(' . $selector . ')';
            $interleaved[] = $literal;
            $interleaved[] = [ $identifier, $selector ];
            $params[] = $identifier;
        }

        $literal = substr($extended, $offset);
        $regex .= preg_quote($literal, '#');
        $interleaved[] = $literal;

        return [ $interleaved, $params, $regex ];
    }

    /**
     * Whether a parameter starts at the offset.
     */
    private static function starts_token(string $extended, int $offset): bool
    {
        return preg_match('#\G(?::\w|<[^>])#', $extended, $_, 0, $offset) === 1;
    }

    /**
     * A constraint must not capture, the captured values are paired with the parameter names.
     *
     * @throws InvalidPattern
     */
    private static function assert_no_capturing_group(string $pattern, string $name, string $constraint): void
    {
        // The empty alternative always matches, and unmatched groups are reported as null.
        if (@preg_match("#(?:$constraint)|#", '', $matches, PREG_UNMATCHED_AS_NULL) === false) {
            throw new InvalidPattern("Invalid constraint for parameter '$name' in pattern: $pattern.");
        }

        if (count($matches) > 1) {
            throw new InvalidPattern(
                "The constraint of parameter '$name' in pattern '$pattern' has a capturing group, "
                . "use a non-capturing group instead: (?:...)."
            );
        }
    }

    /**
     * Checks if the given string is a route pattern.
     */
    public static function is_pattern(string $pattern): bool
    {
        return str_contains($pattern, '<') || str_contains($pattern, ':') || str_contains($pattern, '*');
    }

    /**
     * Patterns are shared while something holds them, e.g. a route. Weak references let them be
     * freed otherwise, {@see __destruct()} removes the entry. A `WeakMap` cannot be used because its
     * keys must be objects.
     *
     * @var array<string, WeakReference<self>>
     */
    private static array $instances = [];

    /**
     * Creates a {@see Pattern} instance from the specified pattern.
     */
    public static function from(string|self $pattern): self
    {
        if ($pattern instanceof self) {
            return $pattern;
        }

        if (!trim($pattern)) {
            throw new InvalidPattern("Pattern cannot be blank.");
        }

        $instance = (self::$instances[$pattern] ?? null)?->get();

        if (!$instance) {
            $instance = new self(...self::parse($pattern));
            self::$instances[$pattern] = WeakReference::create($instance);
        }

        return $instance;
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

    public function __destruct()
    {
        // Instances made by `__set_state()` are not in the cache, they must not evict the cached one.
        if ((self::$instances[$this->pattern] ?? null)?->get() === $this) {
            unset(self::$instances[$this->pattern]);
        }
    }

    /**
     * Returns the route pattern specified during construct.
     */
    public function __toString(): string
    {
        return $this->pattern;
    }

    /**
     * Formats a pattern with the specified values. Values are percent-encoded with `rawurlencode()`.
     *
     * @param array<string|int, mixed>|object|null $values The values to format the pattern, either as an array or an
     * object. If value is an instance of {@see ToSlug} the `to_slug()` method is used to
     * transform the instance into a URL component.
     *
     * @throws PatternRequiresValues in attempt to format a pattern requiring values without
     * providing any.
     * @throws PatternValueMissing if a value is missing.
     * @throws PatternValueNotValid if a value does not satisfy the constraint of its parameter, the
     * resulting URL would not match the pattern.
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
     * @param array<int|string, mixed>|object $values
     *
     * @throws PatternValueMissing
     * @throws PatternValueNotValid
     */
    private function format_parts(array|object $values): string
    {
        $url = '';
        $last = count($this->interleaved) - 2;
        $catchall = str_ends_with($this->pattern, '*');

        foreach ($this->interleaved as $i => $part) {
            if (!is_array($part)) {
                $url .= $part;

                continue;
            }

            $name = $part[0];
            $value = $this->stringify($this->value_of($values, $name));

            if ($catchall && $i === $last) {
                // The catch-all is the only value where `/` is kept.
                $url .= implode('/', array_map(rawurlencode(...), explode('/', $value)));

                continue;
            }

            // The constraint is tested against the encoded value, like the matcher does with the path.
            $encoded = rawurlencode($value);

            if (!preg_match("#^(?:$part[1])$#", $encoded)) {
                throw new PatternValueNotValid($this, $name, $value);
            }

            $url .= $encoded;
        }

        return $url;
    }

    /**
     * @param array<int|string, mixed>|object $values
     *
     * @throws PatternValueMissing
     */
    private function value_of(array|object $values, int|string $name): mixed
    {
        if (is_array($values)) {
            $value = $values[$name] ?? null;
        } else {
            $value = property_exists($values, (string) $name) || isset($values->$name) ? $values->$name : null;
        }

        return $value ?? throw new PatternValueMissing($this, $name);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof ToSlug => $value->to_slug(),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => throw new InvalidArgumentException(
                "Cannot format a value of type " . get_debug_type($value) . " in pattern: $this->pattern."
            ),
        };
    }

    /**
     * Checks if a pathname matches the pattern. The captured values are percent-decoded.
     *
     * @param array<int|string, string>|null $captured The parameters captured from the pathname.
     * @param-out array<int|string, string> $captured
     */
    public function matches(string $pathname, array|null &$captured = []): bool
    {
        $captured = [];

        #
        # `params` is empty if the pattern is a plain string, thus we can compare strings.
        #

        if (!$this->params) {
            return $pathname === $this->pattern;
        }

        if (!preg_match($this->regex, $pathname, $matches)) {
            return false;
        }

        array_shift($matches);

        $captured = $this->captured($matches);

        return true;
    }

    /**
     * Pairs the values captured by {@see $regex} with the names of the parameters.
     *
     * The values are percent-decoded here, after matching, so that an encoded `/` (`%2F`) stays inside
     * its segment. Route providers that run the regex themselves must use this method.
     *
     * @param array<int|string, string> $values The captured values, without the full match.
     *
     * @return array<int|string, string>
     */
    public function captured(array $values): array
    {
        return array_combine($this->params, array_map(rawurldecode(...), $values));
    }
}
