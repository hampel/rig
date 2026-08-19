<?php

declare(strict_types=1);

namespace Hampel\Rig;

use Throwable;

/**
 * Finds the exercise, loads the environment, and runs it.
 *
 * The default is a fresh PHP process, for three reasons that have nothing to do with
 * speed: a fatal error in an exercise cannot take the rig down with it, the exit status
 * belongs to the exercise rather than to the rig, and --php runs the same exercise on
 * another PHP version without reinstalling anything.
 */
class Runner
{
    public const VERSION = '0.1.2';

    private string $root;

    public function __construct(private Io $io)
    {
    }

    /**
     * @param  list<string>  $argv
     */
    public function run(array $argv): int
    {
        $arguments = new Arguments($argv);

        if ($arguments->has('help')) {
            return $this->usage();
        }

        if ($arguments->has('version')) {
            $this->io->line('rig ' . self::VERSION);

            return 0;
        }

        $root = $arguments->option('package') ?? getcwd();

        if ($root === false) {
            $this->io->error('Cannot determine the working directory.');

            return 1;
        }

        $this->root = rtrim($root, '/');

        Environment::load($this->environmentFile($arguments));

        $exercises = new Exercises($this->harnessDirectory($arguments));
        $name = $arguments->first();

        if ($name === null || $arguments->has('list')) {
            return $this->listExercises($exercises);
        }

        $path = $exercises->path($name);

        if ($path === null) {
            $this->io->error("No exercise '{$name}' in {$exercises->directory}");

            if ($exercises->names() !== []) {
                $this->io->line('  Available: ' . implode(', ', $exercises->names()));
            }

            return 1;
        }

        return $arguments->has('in-process')
            ? $this->runHere($path)
            : $this->runIsolated($path, $arguments->option('php') ?? PHP_BINARY);
    }

    private function runIsolated(string $script, string $binary): int
    {
        $stub = tempnam(sys_get_temp_dir(), 'rig');

        if ($stub === false) {
            $this->io->error('Cannot write a temporary file to run the exercise from.');

            return 1;
        }

        file_put_contents($stub, $this->childScript($script));

        passthru(escapeshellarg($binary) . ' ' . escapeshellarg($stub), $status);

        unlink($stub);

        return $status;
    }

    /**
     * In-process, for when the exercise wants the rig's own process - a REPL, a debugger
     * session, xdebug already attached. The exercise's fatal errors are yours to keep.
     */
    private function runHere(string $script): int
    {
        $io = $this->io;
        $autoload = $this->root . '/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        try {
            require $script;
        } catch (Throwable $e) {
            $this->io->error($e::class . ': ' . $e->getMessage());
            $this->io->line('  ' . $e->getFile() . ':' . $e->getLine());

            return 1;
        }

        unset($io);

        return 0;
    }

    /**
     * The child loads the package's autoloader and nothing else. Io comes from that
     * autoloader when the rig is installed as a dev dependency, which is the normal
     * case; the file is required directly only when the rig is running from somewhere
     * the package has never heard of.
     */
    private function childScript(string $script): string
    {
        $autoload = var_export($this->root . '/vendor/autoload.php', true);
        $io = var_export(__DIR__ . '/Io.php', true);
        $exercise = var_export($script, true);
        $decorated = $this->io->isDecorated() ? 'true' : 'false';

        return <<<PHP
        <?php

        \$autoload = {$autoload};

        if (is_file(\$autoload)) {
            require \$autoload;
        }

        if (! class_exists(\Hampel\Rig\Io::class, false)) {
            require {$io};
        }

        \$io = new \Hampel\Rig\Io({$decorated});

        require {$exercise};

        PHP;
    }

    /**
     * An absolute --env is taken as given, the same way --harness is. Concatenating one
     * onto the package produced a path that could not exist, and a missing environment
     * file is not an error - so the exercise ran with none of its credentials set and
     * nothing said why.
     */
    private function environmentFile(Arguments $arguments): string
    {
        $file = $arguments->option('env') ?? '.env';

        return str_starts_with($file, '/')
            ? $file
            : $this->root . '/' . $file;
    }

    private function harnessDirectory(Arguments $arguments): string
    {
        $directory = $arguments->option('harness')
            ?? (getenv('RIG_HARNESS') ?: 'harness');

        return str_starts_with($directory, '/')
            ? rtrim($directory, '/')
            : $this->root . '/' . trim($directory, '/');
    }

    private function listExercises(Exercises $exercises): int
    {
        $this->io->title('rig · ' . basename($this->root));

        if ($exercises->all() === []) {
            $this->io->line('  ' . $exercises->directory);
            $this->io->line();
            $this->io->warn('No exercises here yet.');
            $this->io->line('  An exercise is a .php file in that directory. It is handed one');
            $this->io->line('  variable, $io, and may do whatever it likes - including things a');
            $this->io->line('  test must never do.');

            return 0;
        }

        $this->io->line('  ' . $exercises->directory);
        $this->io->line();

        foreach ($exercises->names() as $name) {
            $this->io->line('  ' . str_pad($name, 18) . $exercises->describe($name));
        }

        $this->io->line();
        $this->io->info('  vendor/bin/rig <exercise>');

        return 0;
    }

    private function usage(): int
    {
        $this->io->title('rig ' . self::VERSION);
        $this->io->line('  Exercise a package by hand, for real.');
        $this->io->line();
        $this->io->line('  vendor/bin/rig                list this package\'s exercises');
        $this->io->line('  vendor/bin/rig <exercise>     run one, in a fresh process');
        $this->io->line();
        $this->io->line('  --in-process                  run in this process instead');
        $this->io->line('  --php=<binary>                run the exercise on another PHP');
        $this->io->line('  --package=<path>              exercise a package somewhere else');
        $this->io->line('  --harness=<dir>               where the exercises are (default: harness)');
        $this->io->line('  --env=<file>                  environment file (default: .env)');
        $this->io->line('  --list                        list the exercises even when one is named');
        $this->io->line('  --version                     print the version');
        $this->io->line('  --help                        print this');

        return 0;
    }
}
