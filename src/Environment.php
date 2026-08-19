<?php

declare(strict_types=1);

namespace Hampel\Rig;

/**
 * The package's own .env, read into the process so an exercise can reach real
 * credentials with getenv().
 *
 * Deliberately minimal - KEY=VALUE, # comments, optional surrounding quotes. No
 * interpolation, no nesting, no multi-line values. A full dotenv implementation is a
 * dependency, and this file exists so that the rig can have none; anything more
 * elaborate than this belongs in the exercise, which is ordinary PHP and can do whatever
 * it likes.
 *
 * Values already present in the environment win, so a one-off run can override the file
 * without editing it: WEBHOOK_URL=... vendor/bin/rig notify
 */
class Environment
{
    /**
     * @return array<string, string> what was loaded, for reporting
     */
    public static function load(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $loaded = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $pair = self::parse($line);

            if ($pair === null) {
                continue;
            }

            [$key, $value] = $pair;

            if (getenv($key) !== false) {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $loaded[$key] = $value;
        }

        return $loaded;
    }

    /**
     * @return array{string, string}|null
     */
    public static function parse(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            return null;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);

        if ($key === '') {
            return null;
        }

        $value = trim($value);

        if (strlen($value) > 1) {
            foreach (['"', "'"] as $quote) {
                if (str_starts_with($value, $quote) && str_ends_with($value, $quote)) {
                    return [$key, substr($value, 1, -1)];
                }
            }
        }

        return [$key, $value];
    }
}
