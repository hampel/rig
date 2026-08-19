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

PHPUnit, `tests/`, namespace `Hampel\Rig\Tests\`, one `#[CoversClass]` per test class and
snake_case `test_` methods. `Arguments`, `Environment` and `Exercises` are pure and are
tested directly. `Io` is tested by handing it a `php://memory` stream — which is why the
constructor takes one; `phpunit.xml` sets
`beStrictAboutOutputDuringTests`, and writing to `STDOUT` would bypass it.

`Runner` is tested for dispatch only: the version, the usage text, the empty-harness
message, the listing, and the unknown-exercise error — every path that returns before
anything is started. `runIsolated()` and `runHere()` have no tests on purpose, because a
real subprocess and a real `require` are what a test can only fake dishonestly, and a
faked one asserts that the mock works. `RunnerTest` necessarily drives the other four
classes, so it declares them with `#[UsesClass]`; without that,
`beStrictAboutCoverageMetadata` makes every one of its tests risky under coverage.

`harness/output.php` covers what a test could not tell you anyway — whether the output
reads well.

## Things that have to move together

Each of these is duplicated somewhere by necessity, and nothing catches the omission:

| changing | also change |
|---|---|
| the `php` constraint in `composer.json` | `phpVersion` min/max in `phpstan.neon`, and the `php` matrix in `.github/workflows/tests.yml` |
| adding a class to `src/` | the `require` fallback list in `bin/rig`, which loads all five by hand when nothing autoloaded them — a checkout with no vendor directory, since a package with no dependencies may legitimately have none |
| adding a dev-only file at the top level | `.gitattributes`, so it stays out of the dist archive |
| releasing | `Runner::VERSION`, which `--version` and the help title print, and `CHANGELOG.md` |

## Conventions

PSR-12 via Pint (`pint.json`), PHPStan level 10, and `declare(strict_types=1)` in every
file under `src/`, `tests/` and `bin/`.

Exercises are exempt, deliberately. `harness/output.php` carries no `declare`, because it
has to match the shape the README documents — an ordinary PHP script with a docblock and
nothing else. Adding ceremony there to satisfy a note here would make every exercise
anyone writes pay for it. Pint does not enforce the declare either way, so this is a
convention rather than a check.

Public documents — README, CHANGELOG — state facts and never name a private application,
an internal host, or an absolute path. Reasoning belongs in commit messages.

## Deliberately undocumented

`Runner::harnessDirectory()` reads `RIG_HARNESS` as an environment fallback for `--harness`.
It is left out of the README on purpose, pending a decision on whether it earns its place.
Do not document it without asking, and do not remove it either.
