<?php

declare(strict_types=1);

namespace Hampel\Rig;

/**
 * Command line parsing, at the scale this tool actually needs it.
 *
 * `rig [exercise] [--option[=value]]`, and nothing else. A dependency on a console
 * component would be a hundred times the size of the thing it parses, and every class it
 * brought would land in the vendor directory of the package under test.
 */
class Arguments
{
    /** @var list<string> */
    private array $arguments = [];

    /** @var array<string, string> */
    private array $options = [];

    /**
     * @param  list<string>  $argv  the raw argv, including the script name
     */
    public function __construct(array $argv)
    {
        foreach (array_slice($argv, 1) as $argument) {
            if (! str_starts_with($argument, '--')) {
                $this->arguments[] = $argument;

                continue;
            }

            $argument = substr($argument, 2);

            if (str_contains($argument, '=')) {
                [$key, $value] = explode('=', $argument, 2);
                $this->options[$key] = $value;

                continue;
            }

            $this->options[$argument] = '';
        }
    }

    public function first(): ?string
    {
        return $this->arguments[0] ?? null;
    }

    public function has(string $option): bool
    {
        return array_key_exists($option, $this->options);
    }

    /**
     * The value given to an option, or null when the option is absent or was passed as a
     * bare flag. A flag and an empty value are deliberately the same thing - `--php=`
     * asking for "the default PHP" is not a distinction worth carrying.
     */
    public function option(string $option): ?string
    {
        $value = $this->options[$option] ?? '';

        return $value === '' ? null : $value;
    }
}
