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

/**
 * A route.
 *
 * @property-read Pattern $pattern The pattern of the route.
 */
class Route extends \ICanBoogie\Object
{
	/**
	 * Pattern of the route.
	 *
	 * @var Pattern
	 */
	private $pattern;

	protected function get_pattern()
	{
		return $this->pattern;
	}

	/**
	 * Controller's class name or function.
	 *
	 * @var string
	 */
	private $controller;

	protected function get_controller()
	{
		return $this->controller;
	}

	/**
	 * Identifier of the route.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Redirect location.
	 *
	 * If the property is defined the route is considered an alias.
	 *
	 * @var string
	 */
	public $location;

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