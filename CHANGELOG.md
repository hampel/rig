CHANGELOG
=========

0.1.1 (2026-08-19)
------------------

**Fixed**

* `--env=<file>` given an absolute path was concatenated onto the package, so the file was
  never found. A missing environment file is not an error, so the exercise ran with none of
  its credentials set and nothing said why. An absolute path is now taken as given, the
  same way `--harness` already treated one

**Documentation**

* the recommended `.gitignore` block for a package with a harness now covers `.env.*` as
  well as `.env`, keeping `.env.example`, because `--env=` invites variants such as
  `.env.staging` that the previous advice left tracked

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
