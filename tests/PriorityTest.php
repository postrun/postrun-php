<?php

namespace PostRun\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PostRun\Priority;

class PriorityTest extends TestCase
{
    public function test_resolves_an_enum_case_to_itself(): void
    {
        $this->assertSame(Priority::High, Priority::fromValue(Priority::High));
    }

    public function test_resolves_names_regardless_of_case_and_whitespace(): void
    {
        $this->assertSame(Priority::Low, Priority::fromValue('low'));
        $this->assertSame(Priority::High, Priority::fromValue(' HIGH '));
        $this->assertSame(Priority::Normal, Priority::fromValue('Normal'));
    }

    public function test_rejects_unknown_priorities(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PostRun priority "urgent". Use one of: high, normal, low.');

        Priority::fromValue('urgent');
    }
}
