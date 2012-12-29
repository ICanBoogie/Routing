<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * A route.
 */
class Route extends Object
{
	static public $contextualize_callback; // TODO-20121128: remove, use ICanBoogie\Routing\contextualize

	/**
	 * Contextualize the route.
	 *
	 * If the #{@link $contextualize_callback} class property is defined, the callback is used to
	 * contextualize the route, otherwise the route is returned as is.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	static public function contextualize($str) // TODO-20121128: remove, use ICanBoogie\Routing\contextualize
	{
		return self::$contextualize_callback ? call_user_func(self::$contextualize_callback, $str) : $str;
	}

	static public $decontextualize_callback; // TODO-20121128: remove, use ICanBoogie\Routing\decontextualize

	/**
	 * Decontextualize the route.
	 *
	 * If the #{@link $decontextualize_callback} class property is defined, the callback is used to
	 * decontextualize the route, otherwise the route is returned as is.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	static public function decontextualize($str) // TODO-20121128: remove, use ICanBoogie\Routing\decontextualize
	{
		return self::$decontextualize_callback ? call_user_func(self::$decontextualize_callback, $str) : $str;
	}

	static private $parse_cache = array();

	/**
	 * Parses a route pattern and return an array of interleaved paths and parameters, parameters
	 * and the regular expression for the specified pattern.
	 *
	 * @param string $pattern The route pattern.
	 *
	 * @return array
	 */
	static public function parse($pattern) // TODO-20121128: as helper, or a Pattern class ?
	{
		if (isset(self::$parse_cache[$pattern]))
		{
			return self::$parse_cache[$pattern];
		}

		$regex = '#^';
		$interleave = array();
		$params = array();
		$n = 0;

		$parts = preg_split('#(:\w+|<(\w+:)?([^>]+)>)#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $j = count($parts); $i < $j ;)
		{
			$part = $parts[$i++];

			$regex .= preg_quote($part, '#');
			$interleave[] = $part;

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
			$interleave[] = array($identifier, $selector);
			$params[] = $identifier;
		}

		$regex .= '$#';

		return self::$parse_cache[$pattern] = array($interleave, $params, $regex);
	}

	/**
	 * Checks if a pathname matches a route pattern.
	 *
	 * @param string $pathname The pathname.
	 * @param string $pattern The pattern of the route.
	 * @param array $captured The parameters captured from the pathname.
	 *
	 * @return boolean
	 */
	static public function match($pathname, $pattern, &$captured=null)
	{
		$captured = array();
		$parsed = self::parse($pattern);

		list(, $params, $regex) = $parsed;

		#
		# $params is empty if the pattern is a plain string, in which case we can do a simple
		# string comparison.
		#

		$match = $params ? preg_match($regex, $pathname, $values) : $pathname === $pattern;

		if (!$match)
		{
			return false;
		}

		if ($params)
		{
			array_shift($values);

			$captured = array_combine($params, $values);
		}

		return true;
	}

	/**
	 * Returns a route formatted using a pattern and values.
	 *
	 * @param string $pattern The route pattern
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 *
	 * @return string The formatted route.
	 */
	static public function format($pattern, $values=null)
	{
		$url = '';
		$parsed = self::parse($pattern);

		if (is_array($values))
		{
			foreach ($parsed[0] as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values[$value[0]]) : $value;
			}
		}
		else
		{
			foreach ($parsed[0] as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values->$value[0]) : $value;
			}
		}

		return $url;
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

	/**
	 * Identifier of the route.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Pattern of the route.
	 *
	 * @var string
	 */
	public $pattern;

	/**
	 * Redirect location.
	 *
	 * If the property is defined the route is considered an alias.
	 *
	 * @var string
	 */
	public $location;

	/**
	 * Class of the controller.
	 *
	 * @var string
	 */
	public $class;

	/**
	 * Callback of the controller.
	 *
	 * @var callable
	 */
	public $callback;

	/**
	 * Request methods accepted by the route.
	 *
	 * @var string
	 */
	public $via;

	/**
	 * Initializes the {@link $pattern} property and the properties provided.
	 *
	 * @param string $pattern
	 * @param array $properties
	 */
	public function __construct($pattern, array $properties)
	{
		$this->pattern = $pattern;

		foreach ($properties as $property => $value)
		{
			$this->$property = $value;
		}
	}

	public function __get($property)
	{
		switch ($property)
		{
			case 'url':
			{
				if (isset($this->url_provider))
				{
					$class = $this->url_provider;
					$provider = new $class();

					return $provider($this);
				}
			}
			break;
		}

		return parent::__get($property);
	}
}