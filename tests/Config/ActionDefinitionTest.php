<?php

namespace Test\ICanBoogie\Routing\Config;

use ICanBoogie\Routing\Config\ActionDefinition;
use ICanBoogie\Routing\Parameter\CurrentRequest;
use ICanBoogie\Routing\Parameter\MatchedRoute;
use ICanBoogie\Routing\Parameter\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Routing\Parameter\StringBackedEnum;
use Test\ICanBoogie\Routing\SetStateHelper;

#[CoversClass(ActionDefinition::class)]
final class ActionDefinitionTest extends TestCase
{
    public function test_properties(): void
    {
        $plan = [ new CurrentRequest(), new Path('id', 'int') ];
        $definition = new ActionDefinition('show', $plan);

        $this->assertSame('show', $definition->method);
        $this->assertSame($plan, $definition->plan);
    }

    public function test_plan_is_empty_by_default(): void
    {
        $this->assertSame([], new ActionDefinition('list')->plan);
    }

    public function test_export(): void
    {
        $definition = new ActionDefinition('show', [
            new CurrentRequest(),
            new MatchedRoute(),
            new Path('id', 'int'),
            new Path('status', StringBackedEnum::class, 'string', true, true, StringBackedEnum::DRAFT),
        ]);

        $actual = SetStateHelper::export_import($definition);

        $this->assertEquals($definition, $actual);
    }
}
