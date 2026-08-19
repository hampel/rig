<?php

declare(strict_types=1);

namespace Hampel\Rig\Tests;

use Hampel\Rig\Arguments;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arguments::class)]
class ArgumentsTest extends TestCase
{
    public function test_it_ignores_the_script_name(): void
    {
        $arguments = new Arguments(['rig']);

        $this->assertNull($arguments->first());
    }

    public function test_it_reads_the_exercise_name(): void
    {
        $arguments = new Arguments(['rig', 'notify']);

        $this->assertSame('notify', $arguments->first());
    }

    public function test_it_reads_a_flag(): void
    {
        $arguments = new Arguments(['rig', 'notify', '--in-process']);

        $this->assertTrue($arguments->has('in-process'));
        $this->assertNull($arguments->option('in-process'));
    }

    public function test_it_reads_an_option_with_a_value(): void
    {
        $arguments = new Arguments(['rig', '--php=php8.5']);

        $this->assertTrue($arguments->has('php'));
        $this->assertSame('php8.5', $arguments->option('php'));
    }

    public function test_it_keeps_an_equals_sign_in_a_value(): void
    {
        $arguments = new Arguments(['rig', '--env=.env.a=b']);

        $this->assertSame('.env.a=b', $arguments->option('env'));
    }

    public function test_an_absent_option_is_null(): void
    {
        $arguments = new Arguments(['rig']);

        $this->assertFalse($arguments->has('php'));
        $this->assertNull($arguments->option('php'));
    }

    public function test_options_do_not_become_arguments(): void
    {
        $arguments = new Arguments(['rig', '--list']);

        $this->assertNull($arguments->first());
    }
}
