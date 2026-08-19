<?php

declare(strict_types=1);

namespace Hampel\Rig;

/**
 * The exercises a package offers: the .php files in its harness directory.
 *
 * They live with the package rather than with the rig, the way tests do, because an
 * exercise is written against the package's own API and is worth nothing without it.
 * Export-ignore the directory and consumers never see it.
 */
class Exercises
{
    public function __construct(public readonly string $directory)
    {
    }

    /**
     * @return array<string, string> name => absolute path, sorted by name
     */
    public function all(): array
    {
        $found = [];

        foreach (glob($this->directory . '/*.php') ?: [] as $path) {
            $found[basename($path, '.php')] = $path;
        }

        ksort($found);

        return $found;
    }

    public function path(string $name): ?string
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * What an exercise says it does: the text following "Exercise:" in its docblock.
     *
     * A convention rather than a requirement - an exercise with no docblock still runs,
     * it just has nothing to say for itself in the listing.
     */
    public function describe(string $name): string
    {
        $path = $this->path($name);

        if ($path === null) {
            return '';
        }

        $source = file_get_contents($path);

        if ($source === false) {
            return '';
        }

        if (preg_match('/\*\s*Exercise:\s*(.+)/', $source, $matches) !== 1) {
            return '';
        }

        return trim($matches[1]);
    }
}
