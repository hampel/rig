<?php

declare(strict_types=1);

namespace Hampel\Rig\Tests;

use Hampel\Rig\Io;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Io::class)]
class IoTest extends TestCase
{
    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        $this->stream = $stream;
    }

    private function io(bool $decorated = false): Io
    {
        return new Io($decorated, $this->stream);
    }

    private function written(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }

    public function test_it_writes_a_line(): void
    {
        $this->io()->line('hello');

        $this->assertSame('hello' . PHP_EOL, $this->written());
    }

    public function test_it_leaves_output_undecorated_when_asked(): void
    {
        $this->io()->error('nope');

        $this->assertSame('  nope' . PHP_EOL, $this->written());
        $this->assertFalse($this->io()->isDecorated());
    }

    public function test_it_decorates_when_asked(): void
    {
        $this->io(true)->error('nope');

        $this->assertStringContainsString("\033[31m", $this->written());
    }

    public function test_attempt_reports_what_came_back(): void
    {
        $this->io()->attempt('encode', fn (): string => 'result');

        $output = $this->written();

        $this->assertStringContainsString('✓ encode', $output);
        $this->assertStringContainsString("'result'", $output);
    }

    public function test_attempt_reports_a_throwable_as_an_outcome(): void
    {
        $this->io()->attempt('explode', function (): never {
            throw new RuntimeException('boom', 42);
        });

        $output = $this->written();

        $this->assertStringContainsString('✗ explode', $output);
        $this->assertStringContainsString(RuntimeException::class, $output);
        $this->assertStringContainsString('boom', $output);
        $this->assertStringContainsString('42', $output);
    }

    public function test_it_stringifies_the_types_an_exercise_returns(): void
    {
        $io = $this->io();

        $this->assertSame("'text'", $io->stringify('text'));
        $this->assertSame('true', $io->stringify(true));
        $this->assertSame('false', $io->stringify(false));
        $this->assertSame('null', $io->stringify(null));
        $this->assertSame('42', $io->stringify(42));
        $this->assertStringContainsString('a', $io->stringify(['a' => 1]));
    }
}
