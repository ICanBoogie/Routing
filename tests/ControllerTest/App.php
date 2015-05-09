<?php

namespace ICanBoogie\Routing\ControllerTest;

use ICanBoogie\Accessor\AccessorTrait;

class App
{
	use AccessorTrait;

	private $value;

	protected function get_value()
	{
		return $this->value;
	}

	public function __construct($value)
	{
		$this->value = $value;
	}
}
