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

    public function test_it_renders_an_array_on_one_line(): void
    {
        $io = $this->io();

        $this->assertSame('[]', $io->stringify([]));
        $this->assertSame('[2, 3]', $io->stringify([2, 3]));
        $this->assertSame("['a' => 1, 'b' => [2, 3]]", $io->stringify(['a' => 1, 'b' => [2, 3]]));
    }

    /**
     * The point of the whole exercise: value() aligns a label column, so a value that
     * wraps onto a second line breaks every row after it.
     */
    public function test_a_value_never_breaks_the_aligned_column(): void
    {
        $this->io()->value('array', ['a' => 1, 'b' => [2, 3]]);

        $this->assertSame("  array         ['a' => 1, 'b' => [2, 3]]" . PHP_EOL, $this->written());
    }

    public function test_it_caps_depth_and_length(): void
    {
        $io = $this->io();

        $this->assertSame("['a' => ['b' => ['c' => […]]]]", $io->stringify(['a' => ['b' => ['c' => ['d' => 1]]]]));
        $this->assertSame('[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, … +2 more]', $io->stringify(range(1, 12)));
    }

    /**
     * A self-referencing array terminates on the depth cap rather than on a guard of its
     * own - which is the reason the cap is not merely cosmetic.
     */
    public function test_it_survives_a_recursive_array(): void
    {
        $array = ['a' => 1];
        $array['self'] = &$array;

        $this->assertStringNotContainsString(PHP_EOL, $this->io()->stringify($array));
    }

    public function test_it_renders_an_object_by_class_and_a_stringable_by_value(): void
    {
        $io = $this->io();

        $this->assertSame(Io::class, $io->stringify($io));
        $this->assertStringContainsString("('five')", $io->stringify(new class () {
            public function __toString(): string
            {
                return 'five';
            }
        }));
    }

    /**
     * A throwable's __toString() is its message, file, line and the whole stack trace,
     * which in an aligned column buries every line after it. An exercise that shows what
     * a package throws is the most ordinary thing there is to write here.
     */
    public function test_it_renders_a_throwable_by_class_and_message(): void
    {
        $this->assertSame(
            "RuntimeException('the cause')",
            $this->io()->stringify(new \RuntimeException('the cause')),
        );
    }

    /**
     * The invariant value() depends on, asserted for every kind of value that can carry a
     * newline rather than for arrays alone.
     */
    public function test_no_value_ever_returns_a_newline(): void
    {
        $io = $this->io();

        $values = [
            "first\nsecond",
            new \RuntimeException("a message\nover two lines"),
            new class () {
                public function __toString(): string
                {
                    return "line\nbreak";
                }
            },
            ['key' => "value\nhere"],
        ];

        foreach ($values as $value) {
            $this->assertStringNotContainsString("\n", $io->stringify($value));
        }
    }

    public function test_it_shows_a_line_break_as_an_escape_rather_than_dropping_it(): void
    {
        $io = $this->io();

        $this->assertSame("'first\\nsecond'", $io->stringify("first\nsecond"));
        $this->assertSame("'a\\tb'", $io->stringify("a\tb"));
        $this->assertSame("'Hampel\\Rig\\Io'", $io->stringify('Hampel\Rig\Io'));
    }

    /**
     * str_pad pads *to* a width, so a label that fills the column got no separator at all
     * and ran straight into its value. A shorter label keeps the output it always had.
     */
    public function test_a_label_that_fills_the_column_still_has_a_separator(): void
    {
        $io = $this->io();

        $io->value('short', 'value');
        $io->value('exactly14chars', 'value');
        $io->value('json_last_error', 'value');

        $this->assertSame(
            "  short         'value'" . PHP_EOL
            . "  exactly14chars 'value'" . PHP_EOL
            . "  json_last_error 'value'" . PHP_EOL,
            $this->written(),
        );
    }
}
