<?php

declare(strict_types=1);

namespace Hampel\Rig;

use Throwable;

/**
 * The entire contract between the rig and an exercise script.
 *
 * An exercise is handed one of these as $io and needs nothing else. Keeping the contract
 * to a single class with no dependencies is what lets an exercise run in a subprocess
 * that has never loaded the rig, and - more importantly - what keeps the rig from
 * putting anything into the package's vendor directory that could mask a dependency the
 * package uses but never declared.
 *
 * Nothing here judges an outcome. The rig reports what happened and a person decides
 * what it means; a tool that decides for you is a test runner, which this is not.
 */
class Io
{
    /** @var resource */
    private $stream;

    /**
     * @param  resource|null  $stream  defaults to STDOUT
     */
    public function __construct(private bool $decorated = true, $stream = null)
    {
        $this->stream = $stream ?? STDOUT;
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function line(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function title(string $text): void
    {
        $this->line();
        $this->line($this->style($text, '1;36'));
        $this->line($this->style(str_repeat('-', strlen($text)), '36'));
    }

    public function info(string $text): void
    {
        $this->line($this->style($text, '36'));
    }

    public function success(string $text): void
    {
        $this->line($this->style('  ' . $text, '32'));
    }

    public function warn(string $text): void
    {
        $this->line($this->style('  ' . $text, '33'));
    }

    public function error(string $text): void
    {
        $this->line($this->style('  ' . $text, '31'));
    }

    /**
     * A label and a value, aligned - the shape most exercise output takes.
     */
    public function value(string $label, mixed $value): void
    {
        $this->line('  ' . str_pad($label, 14) . $this->style($this->stringify($value), '1'));
    }

    /**
     * Run something and report what came back, or what was thrown.
     *
     * A throwable is an outcome here, not a failure: the error paths are usually what
     * you came to look at. Nothing is asserted and nothing exits non-zero.
     */
    public function attempt(string $description, callable $callback): void
    {
        try {
            $result = $callback();
            $this->success('✓ ' . $description);
            $this->value('returned', $result);
        } catch (Throwable $e) {
            $this->warn('✗ ' . $description);
            $this->value('threw', $e::class);
            $this->value('message', $e->getMessage());
            $this->value('code', $e->getCode());
        }
    }

    public function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => "'" . $value . "'",
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            is_object($value) && ! method_exists($value, '__toString') => $value::class,
            default => trim(print_r($value, true)),
        };
    }

    private function style(string $text, string $code): string
    {
        return $this->decorated ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
