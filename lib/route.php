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

use ICanBoogie\Routing\Pattern;

/**
 * A route.
 *
 * @property-read \ICanBooogie\Routing\Pattern $pattern The pattern of the route.
 */
class Route extends Object
{
	/**
	 * @deprecated
	 */
	static public function parse($pattern)
	{
		$pattern = Pattern::from($pattern);

		return array($pattern->interleaved, $pattern->params, $pattern->regex);
	}

	/**
	 * @deprecated
	 */
	static public function match($pathname, $pattern, &$captured=null)
	{
		$pattern = Pattern::from($pattern);

		return $pattern->match($pathname, $captured);
	}

	/**
	 * @deprecated
	 */
	static public function format_pattern($pattern, $values=null)
	{
		return Pattern::from($pattern)->format($values);
	}

	/**
	 * @deprecated
	 */
	static public function is_pattern($pattern)
	{
		return Pattern::is_pattern($pattern);
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
	 * @var \ICanBooogie\Routing\Pattern
	 */
	private $pattern;

	protected function volatile_get_pattern()
	{
		return $this->pattern;
	}

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
		$this->pattern = Pattern::from($pattern);

		unset($properties['pattern']);

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

	/**
	 * Returns the pattern of the route.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->pattern;
	}

	/**
	 * Formats the route with the specified values.
	 *
	 * Note: The formatting of the route is defered to its {@link Pattern} instance.
	 *
	 * @return string
	 */
	public function format($values=null)
	{
		return $this->pattern->format($values);
	}
}