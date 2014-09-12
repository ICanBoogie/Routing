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

use ICanBoogie\PropertyNotReadable;

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
 * $matching = $pattern->match($pathname, $captured);
 *
 * var_dump($matching); // true
 * var_dump($captured); // [ 'year' => "2013", 'month' => "07", 'slug' => "test-is-a-test" ]
 * </pre>
 *
 * @property-read string $pattern The pattern.
 * @property-read array $interleaved The interleaved parts of the pattern.
 * @property-read array $params The names of the pattern params.
 * @property-read string $regex The regex of the pattern.
 */
class Pattern
{
	/**
	 * Parses a route pattern and returns an array of interleaved paths and parameters, the
	 * parameter names and the regular expression for the specified pattern.
	 *
	 * @param string $pattern A pattern.
	 *
	 * @return array
	 */
	static private function parse($pattern)
	{
		$regex = '#^';
		$interleaved = [];
		$params = [];
		$n = 0;
		$catchall = false;

		if ($pattern{strlen($pattern) - 1} == '*')
		{
			$catchall = true;
			$pattern = substr($pattern, 0, -1);
		}

		$parts = preg_split('#(:\w+|<(\w+:)?([^>]+)>)#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $j = count($parts); $i < $j ;)
		{
			$part = $parts[$i++];

			$regex .= preg_quote($part, '#');
			$interleaved[] = $part;

			if ($i == $j)
			{
				break;
			}

			$part = $parts[$i++];

			if ($part{0} == ':')
			{
				$identifier = substr($part, 1);
				$separator = $parts[$i];
				$selector = $separator ? '[^/\\' . $separator{0} . ']+' : '[^/]+';
			}
			else
			{
				$identifier = substr($parts[$i++], 0, -1);

				if (!$identifier)
				{
					$identifier = $n++;
				}

				$selector = $parts[$i++];
			}

			$regex .= '(' . $selector . ')';
			$interleaved[] = [ $identifier, $selector ];
			$params[] = $identifier;
		}

		if (!$catchall)
		{
			$regex .= '$';
		}

		$regex .= '#';

		return [ $interleaved, $params, $regex ];
	}

	/**
	 * Checks if the given string is a route pattern.
	 *
	 * @param string $pattern
	 *
	 * @return bool `true` if the given pattern is a route pattern, `false` otherwise.
	 */
	static public function is_pattern($pattern)
	{
		return (strpos($pattern, '<') !== false) || (strpos($pattern, ':') !== false);
	}

	static private $instances;

	/**
	 * Creates a {@link Pattern} instance from the specified pattern.
	 *
	 * @param mixed $pattern
	 *
	 * @return \ICanBoogie\Pattern
	 */
	static public function from($pattern)
	{
		if ($pattern instanceof static)
		{
			return $pattern;
		}

		if (isset(self::$instances[$pattern]))
		{
			return self::$instances[$pattern];
		}

		return self::$instances[$pattern] = new static($pattern);
	}

	/**
	 * Pattern.
	 *
	 * @var string
	 */
	protected $pattern;

	/**
	 * Interleaved pattern.
	 *
	 * @var array
	 */
	protected $interleaved;

	/**
	 * Params of the pattern.
	 *
	 * @var array
	 */
	protected $params;

	/**
	 * Regex of the pattern.
	 *
	 * @var string
	 */
	protected $regex;

	/**
	 * Initializes the {@link $pattern}, {@link $interleaved}, {@link $params} and {@link $regex}
	 * properties.
	 *
	 * @param string $pattern A route pattern.
	 */
	protected function __construct($pattern)
	{
		list($interleaved, $params, $regex) = self::parse($pattern);

		$this->pattern = $pattern;
		$this->interleaved = $interleaved;
		$this->params = $params;
		$this->regex = $regex;
	}

	/**
	 * Returns the route pattern specified during construct.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->pattern;
	}

	public function __get($property)
	{
		static $gettable = [ 'pattern', 'interleaved', 'params', 'regex' ];

		if (!in_array($property, $gettable))
		{
			throw new PropertyNotReadable([ $property, $this ]);
		}

		return $this->$property;
	}

	/**
	 * Formats a pattern with the specified values.
	 *
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 *
	 * @return string
	 */
	public function format($values=null)
	{
		$url = '';

		if (is_array($values))
		{
			foreach ($this->interleaved as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values[$value[0]]) : $value;
			}
		}
		else
		{
			foreach ($this->interleaved as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values->$value[0]) : $value;
			}
		}

		return $url;
	}

	/**
	 * Checks if a pathname matches the pattern.
	 *
	 * @param string $pathname The pathname.
	 * @param array $captured The parameters captured from the pathname.
	 *
	 * @return boolean `true` if the pathname matches the pattern, `false` otherwise.
	 */
	public function match($pathname, &$captured=null)
	{
		#
		# `params` is empty if the pattern is a plain string,
		# thus we can simply compare strings.
		#

		if (!$this->params)
		{
			return $pathname === $this->pattern;
		}

		if (!preg_match($this->regex, $pathname, $matches))
		{
			return false;
		}

		array_shift($matches);

		$captured = array_combine($this->params, $matches);

		return true;
	}
}