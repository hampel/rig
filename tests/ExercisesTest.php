<?php

declare(strict_types=1);

namespace Hampel\Rig\Tests;

use Hampel\Rig\Exercises;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exercises::class)]
class ExercisesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rig-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (array_diff(scandir($this->directory) ?: [], ['.', '..']) as $entry) {
            unlink($this->directory . '/' . $entry);
        }

        rmdir($this->directory);
    }

    private function write(string $name, string $contents = '<?php'): void
    {
        file_put_contents($this->directory . '/' . $name, $contents);
    }

    public function test_an_empty_directory_offers_nothing(): void
    {
        $this->assertSame([], (new Exercises($this->directory))->all());
        $this->assertSame([], (new Exercises($this->directory))->names());
    }

    public function test_a_missing_directory_is_not_an_error(): void
    {
        $this->assertSame([], (new Exercises('/nonexistent'))->all());
    }

    public function test_it_finds_php_files_and_nothing_else(): void
    {
        $this->write('notify.php');
        $this->write('README.md');
        $this->write('.env');

        $this->assertSame(['notify'], (new Exercises($this->directory))->names());
    }

    public function test_it_sorts_by_name(): void
    {
        $this->write('send.php');
        $this->write('build.php');

        $this->assertSame(['build', 'send'], (new Exercises($this->directory))->names());
    }

    public function test_it_resolves_a_path(): void
    {
        $this->write('notify.php');

        $this->assertSame($this->directory . '/notify.php', (new Exercises($this->directory))->path('notify'));
        $this->assertNull((new Exercises($this->directory))->path('nope'));
    }

    public function test_it_reads_the_description_from_the_docblock(): void
    {
        $this->write('notify.php', "<?php\n\n/**\n * Exercise: post a real message.\n */\n");

        $this->assertSame('post a real message.', (new Exercises($this->directory))->describe('notify'));
    }

    public function test_an_exercise_without_a_docblock_still_lists(): void
    {
        $this->write('notify.php', "<?php\n\necho 'hello';\n");

        $this->assertSame('', (new Exercises($this->directory))->describe('notify'));
        $this->assertSame(['notify'], (new Exercises($this->directory))->names());
    }

    public function test_describing_something_that_does_not_exist(): void
    {
        $this->assertSame('', (new Exercises($this->directory))->describe('nope'));
    }
}
