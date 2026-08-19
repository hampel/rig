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
    /**
     * Where an inline array stops. Deep enough to see the shape of a nested response,
     * shallow enough that the line stays a line - and it is what terminates a recursive
     * array, which needs no separate guard.
     */
    private const MAX_DEPTH = 3;

    private const MAX_ITEMS = 10;

    /** The label column value() aligns to. */
    private const LABEL_WIDTH = 14;

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
     *
     * Exactly one line, whatever the value is. str_pad pads *to* a width, so a label of
     * 14 characters or more used to run straight into its value with no separator at
     * all; a label short enough to be padded is untouched, which keeps every existing
     * exercise's output identical.
     */
    public function value(string $label, mixed $value): void
    {
        $label = strlen($label) >= self::LABEL_WIDTH
            ? $label . ' '
            : str_pad($label, self::LABEL_WIDTH);

        $this->line('  ' . $label . $this->style($this->stringify($value), '1'));
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

    /**
     * A value on one line, in the syntax you would have written it in.
     *
     * Never returns a newline. value() aligns a label column and anything multi-line
     * destroys it, so a line break inside a string is shown escaped rather than dropped
     * - what you are looking at is still the whole value. A backslash is left as it is:
     * exercises print class names constantly, and 'Hampel\Rig\Io' reads better than the
     * ambiguity costs.
     */
    public function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $this->quote($value),
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_scalar($value) => (string) $value,
            is_array($value) => $this->inline($value),
            /*
             * Ahead of the __toString() branch, because a throwable's is its message,
             * file, line and the entire stack trace - pages of it, in the value column.
             * Rendered the way attempt() already renders one.
             */
            $value instanceof Throwable => $value::class . '(' . $this->quote($value->getMessage()) . ')',
            is_object($value) => method_exists($value, '__toString')
                ? $value::class . '(' . $this->quote((string) $value) . ')'
                : $value::class,
            default => get_debug_type($value),
        };
    }

    /**
     * A string as a quoted literal, with the characters that would break the line shown
     * as escapes.
     */
    private function quote(string $value): string
    {
        return "'" . strtr($value, ["\n" => '\n', "\r" => '\r', "\t" => '\t']) . "'";
    }

    /**
     * An array on one line, in the syntax you would have written it in.
     *
     * value() aligns a label column, and anything multi-line breaks it - which is what
     * print_r did here. Depth and length are capped so that an exercise returning a large
     * API response still prints something a person can read; strings are not truncated,
     * because the payload is usually the thing you came to look at.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function inline(array $value, int $depth = 0): string
    {
        if ($value === []) {
            return '[]';
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[…]';
        }

        $list = array_is_list($value);
        $parts = [];

        foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $key => $item) {
            $rendered = is_array($item) ? $this->inline($item, $depth + 1) : $this->stringify($item);

            $parts[] = $list ? $rendered : $this->stringify($key) . ' => ' . $rendered;
        }

        if (count($value) > self::MAX_ITEMS) {
            $parts[] = '… +' . (count($value) - self::MAX_ITEMS) . ' more';
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function style(string $text, string $code): string
    {
        return $this->decorated ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
