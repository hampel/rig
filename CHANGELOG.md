CHANGELOG
=========

0.1.0 (2026-08-19)
------------------

First release.

* `vendor/bin/rig` lists and runs exercises from a package's `harness/` directory
* exercises are plain PHP scripts, handed one variable: `$io`
* `Io` — `title()`, `line()`, `info()`, `success()`, `warn()`, `error()`, `value()`,
  `attempt()`, `write()` and `stringify()`
* `attempt()` reports what a callable returned or what it threw, and asserts nothing
* each exercise runs in a fresh PHP process by default
* `--in-process`, `--php`, `--package`, `--harness`, `--env`, `--list`, `--version`, `--help`
* `.env` beside the package is loaded into `getenv()`; values already set in the
  environment take precedence
* requires PHP 8.3 or later, and no other package
