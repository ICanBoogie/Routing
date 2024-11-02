<?php

namespace Test\ICanBoogie\Routing;

use ICanBoogie\Routing\Pattern;
use ICanBoogie\Routing\PatternRequiresValues;
use PHPUnit\Framework\TestCase;

class PatternRequiresValuesTest extends TestCase
{
    public function test_instance(): void
    {
        $pattern = Pattern::from('/:year-:month.html');
        $instance = new PatternRequiresValues($pattern);
        $this->assertSame($pattern, $instance->pattern);
    }
}
