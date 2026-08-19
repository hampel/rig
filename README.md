# rig

[![Tests](https://github.com/hampel/rig/actions/workflows/tests.yml/badge.svg)](https://github.com/hampel/rig/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hampel/rig.svg?style=flat-square)](https://packagist.org/packages/hampel/rig)
[![Total Downloads](https://img.shields.io/packagist/dt/hampel/rig.svg?style=flat-square)](https://packagist.org/packages/hampel/rig)

Exercise a package by hand, for real.

A rig is a small harness you install into a package under development so you can drive its
code the way a consumer would — and watch what actually happens. Exercises are plain PHP
scripts that live with the package. They are meant to have side effects: post the message,
call the API, write the file. That is the point.

This is not a test runner, and it is not trying to become one. A test asserts, runs
unattended, and reports a verdict — so it must not touch anything real. A rig does the
opposite: it does the real thing and shows you the result, and you decide what it means.
Both are worth having, and neither substitutes for the other.

## Install

```bash
composer require --dev hampel/rig
```

It has no dependencies. Nothing but PHP is added to your vendor directory, which matters:
a harness that dragged a framework in would put hundreds of classes where your package's
static analysis can see them, and your package would stop being able to tell you it used
something it never declared.

## Use

Write exercises as `.php` files in a `harness/` directory in your package:

```php
<?php

/**
 * Exercise: post a notification and show what came back.
 *
 * @var Hampel\Rig\Io $io
 */

use Acme\Notifier\Webhook;

$io->title('acme/notifier · post');

$webhook = new Webhook(getenv('WEBHOOK_URL'));

$io->attempt('post a message', fn () => $webhook->post('rig was here'));
```

Then:

```bash
vendor/bin/rig                  # list the exercises this package offers
vendor/bin/rig post             # run one
vendor/bin/rig post --php=php8.4   # run it on another PHP
```

Each exercise is handed one variable, `$io`. Everything else is ordinary PHP — the package
is autoloaded, so use it however a consumer would.

### Options

| option | |
|---|---|
| `--in-process` | run in the rig's own process rather than a fresh one |
| `--php=<binary>` | run the exercise on another PHP binary |
| `--package=<path>` | exercise a package in another directory |
| `--harness=<dir>` | where the exercises are; default `harness` |
| `--env=<file>` | environment file to load; default `.env` |
| `--list` | list exercises even when one is named |
| `--version`, `--help` | |

## Credentials

Exercises that do real things need real credentials. `rig` reads a `.env` beside the
package before running, into `getenv()`:

```
WEBHOOK_URL=https://example.test/services/T000/B000/XXXX
```

Values already set in the environment win, so a single run can override the file without
editing it:

```bash
WEBHOOK_URL=https://example.test/other vendor/bin/rig post
```

**Ignore environment files when you add `harness/`**, whether or not anything needs
credentials yet. A harness that starts out driving pure code grows an API call eventually,
and by then the file is already tracked — the leak happens at the commit that adds the
first real value, not at the one that adds the rule. In `.gitignore`:

```
.env
.env.*
!.env.example
```

None of those are anchored with a leading `/`, on purpose: that would cover the repository
root only, and `--env=` resolves relative to the package, so an environment file can
legitimately sit in a subdirectory. `.env.*` catches the variants the option invites —
`.env.staging`, `.env.local` — and the last line keeps a committed `.env.example`
documenting which variables an exercise expects.

This holds while environment files are named `.env` or `.env.<something>`. `--env=` will
load any file you point it at, and one named otherwise is not covered.

The parser is deliberately minimal — `KEY=VALUE`, `#` comments, optional surrounding
quotes, nothing else. An exercise is ordinary PHP and can read configuration however it
likes if it needs more.

## Keeping the harness out of the dist archive

Exercises belong in the repository and not in the tarball your consumers install. In
`.gitattributes`:

```
/harness export-ignore
```

Two pieces of repository metadata, then — this one, and the environment-file block in
`.gitignore`. Add both when you create `harness/`, so neither is waiting on the day it
turns out to matter.

## Why a fresh process by default

Each exercise runs in its own PHP process. Not for speed — for three properties worth
having:

- a fatal error in an exercise cannot take the rig down with it, so you see the error
- the exit status is the exercise's, not the rig's
- `--php` can point at any PHP binary, which is how you drive the same exercise at both
  ends of the range your package claims to support

`--in-process` opts out, for when you want the exercise inside the current process — an
attached debugger, or an exercise that opens a REPL.

## Requirements

PHP 8.3 or later. No dependencies.

## Licence

MIT. See `LICENSE.md`.
