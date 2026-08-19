# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`rig` runs exercises: plain PHP scripts that drive a package's real code and show a person
what happened. It is installed as a dev dependency of the package being exercised and run
as `vendor/bin/rig`.

It is deliberately **not** a test runner, and the distinction decides most design
questions. A test asserts, runs unattended, produces a verdict, and must not touch
anything real. An exercise does the real thing — posts the message, calls the API — and
produces output for a person to read. Nothing in this package may grow an assertion API,
a pass/fail tally, or suite semantics. `Io::attempt()` sits at that line on purpose: it
reports what returned or what was thrown, and never judges it.

## Commands

```bash
composer check          # lint, analyse, test - what CI runs
composer test           # phpunit
composer analyse        # phpstan, level 10, against the 8.3-8.5 range in one pass
composer lint           # pint --test
composer format         # pint

./bin/rig               # the rig's own harness
./bin/rig output        # look at every shape of output Io can produce
vendor/bin/phpunit --filter=test_it_strips_matching_quotes
```

## The zero-dependency rule

`composer.json` requires `php` and nothing else, and it must stay that way.

The rig installs into the vendor directory of the package it exercises. Anything it
depends on lands there too — visible to that package's PHPStan, autoloadable by its code,
and able to satisfy a `use` statement the package never declared a dependency for. A rig
built on a console component or a framework would quietly break the one property that
makes a package's static analysis trustworthy.

This is why `Arguments` parses argv by hand, `Environment` parses `.env` by hand, and
`Io` writes escape codes by hand. Each is smaller than the dependency it replaces. If one
grows past that point, the answer is to make the feature smaller, not to add a package.

The same rule sets the PHP floor: `>=8.3`, matching the packages this exercises. A dev
tool whose floor is above the floor of the package depending on it makes that floor
untestable.

## Architecture

Five classes, each doing one thing:

| | |
|---|---|
| `Io` | the entire contract with an exercise script — the only class an exercise sees |
| `Arguments` | `rig [exercise] [--option[=value]]`, and nothing else |
| `Environment` | `.env` into `getenv()`; existing environment values win |
| `Exercises` | the `.php` files in the harness directory, and their `Exercise:` descriptions |
| `Runner` | resolve, then run — in a subprocess by default |

`Io` is the piece to be careful with. It is copied into the child process when the rig is
running somewhere the package has never heard of, so it must stay a single file with no
imports beyond `Throwable`.

### Why a subprocess by default

Not speed. A fatal in an exercise cannot take the rig with it, the exit status is the
exercise's own, and `--php` can run the same exercise on any installed PHP. `--in-process`
exists for a debugger or a REPL.

The child receives the package's autoloader and, if that autoloader does not already
provide `Io` (it does whenever the rig is a dev dependency), the `Io` source file
directly. It receives nothing else — which is what keeps an undeclared dependency in the
package failing loudly instead of resolving against something the rig brought.

## Testing

PHPUnit, `tests/`, namespace `Hampel\Rig\Tests\`. `Arguments`, `Environment` and
`Exercises` are pure and are tested directly. `Io` is tested by handing it a
`php://memory` stream — which is why the constructor takes one; `phpunit.xml` sets
`beStrictAboutOutputDuringTests`, and writing to `STDOUT` would bypass it.

`Runner` has no direct tests: it is orchestration over the four, and its two interesting
behaviours (a real subprocess, a real `require`) are exactly what a test cannot honestly
fake. `harness/output.php` covers what a test could not tell you anyway — whether the
output reads well.

## Conventions

PSR-12 via Pint (`pint.json`), `declare(strict_types=1)` in every file, PHPStan level 10
with the `phpVersion` range in `phpstan.neon` kept in step with the `php` constraint.

Public documents — README, CHANGELOG — state facts and never name a private application,
an internal host, or an absolute path. Reasoning belongs in commit messages.
