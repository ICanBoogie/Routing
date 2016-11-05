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

class PatternRequiresValuesTest extends \PHPUnit\Framework\TestCase
{
	public function test_instance()
	{
		$pattern = Pattern::from('/:year-:month.html');
		$instance = new PatternRequiresValues($pattern);
		$this->assertSame($pattern, $instance->pattern);
		$this->assertSame(500, $instance->getCode());
	}
}
