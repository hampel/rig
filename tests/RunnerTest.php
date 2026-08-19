<?php

declare(strict_types=1);

namespace Hampel\Rig\Tests;

use Hampel\Rig\Arguments;
use Hampel\Rig\Environment;
use Hampel\Rig\Exercises;
use Hampel\Rig\Io;
use Hampel\Rig\Runner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The dispatch half of Runner: what it does before it decides to start anything.
 *
 * Running an exercise is deliberately not tested - a real subprocess and a real require
 * are the two things a test can only fake dishonestly, and faking them would assert that
 * the mock works. Everything here returns without touching either, which leaves the
 * messages a person meets when they get the command wrong, and those are worth pinning.
 */
#[CoversClass(Runner::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Exercises::class)]
#[UsesClass(Io::class)]
class RunnerTest extends TestCase
{
    private string $root;

    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rig-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/harness', recursive: true);

        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        $this->stream = $stream;
    }

    protected function tearDown(): void
    {
        $harness = $this->root . '/harness';

        foreach (array_diff(scandir($harness) ?: [], ['.', '..']) as $entry) {
            unlink($harness . '/' . $entry);
        }

        rmdir($harness);
        rmdir($this->root);
    }

    private function rig(string ...$arguments): int
    {
        $argv = array_values(array_merge(['rig'], $arguments));

        return (new Runner(new Io(false, $this->stream)))->run($argv);
    }

    private function written(): string
    {
        rewind($this->stream);

        return (string) stream_get_contents($this->stream);
    }

    private function exercise(string $name, string $description = ''): void
    {
        $source = $description === ''
            ? '<?php'
            : "<?php\n\n/**\n * Exercise: {$description}\n */\n";

        file_put_contents($this->root . '/harness/' . $name . '.php', $source);
    }

    public function test_it_reports_its_version(): void
    {
        $this->assertSame(0, $this->rig('--version'));
        $this->assertStringContainsString(Runner::VERSION, $this->written());
    }

    /**
     * Once installed, usage is the only place an option can be discovered - the README is
     * a click away rather than a command away - so an option the code honours and the
     * usage text omits may as well not exist.
     */
    public function test_usage_lists_every_option_the_runner_honours(): void
    {
        $this->assertSame(0, $this->rig('--help'));

        $output = $this->written();

        $this->assertStringContainsString('vendor/bin/rig', $output);

        foreach (['--in-process', '--php', '--package', '--harness', '--env', '--list', '--version', '--help'] as $option) {
            $this->assertStringContainsString($option, $output);
        }
    }

    public function test_an_empty_harness_says_so_rather_than_nothing(): void
    {
        $this->assertSame(0, $this->rig('--package=' . $this->root));
        $this->assertStringContainsString('No exercises here yet.', $this->written());
    }

    public function test_it_lists_what_it_finds_with_descriptions(): void
    {
        $this->exercise('send', 'post a real message.');
        $this->exercise('build');

        $this->assertSame(0, $this->rig('--package=' . $this->root));

        $output = $this->written();

        $this->assertStringContainsString('build', $output);
        $this->assertStringContainsString('send', $output);
        $this->assertStringContainsString('post a real message.', $output);
    }

    /**
     * The one path here that is a failure rather than a listing, so the exit status is
     * the part that matters: a typo has to be distinguishable from a run that worked.
     */
    public function test_an_unknown_exercise_is_an_error_naming_the_alternatives(): void
    {
        $this->exercise('send');

        $this->assertSame(1, $this->rig('nope', '--package=' . $this->root));

        $output = $this->written();

        $this->assertStringContainsString("No exercise 'nope'", $output);
        $this->assertStringContainsString('Available: send', $output);
    }

    public function test_list_wins_over_a_named_exercise(): void
    {
        $this->exercise('send');

        $this->assertSame(0, $this->rig('send', '--list', '--package=' . $this->root));
        $this->assertStringContainsString('send', $this->written());
    }
}
