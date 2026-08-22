CHANGELOG
=========

0.2.0 (2026-08-22)
------------------

**Changed**

* the environment file is no longer loaded when `CLAUDECODE` is set — the variable Claude
  Code exports into every shell it opens. The file is written by whoever owns the
  credentials in it and generally authorises the real effect, because that is how they run
  their own exercises; an agent inherits that authorisation without having made the
  decision. The exercise still runs, on whatever defaults its own code chooses. With the
  variable absent nothing changes

**Added**

* `--agent-may-load-env`, which loads the file regardless, for an agent that has been asked
  to do the real thing

0.1.2 (2026-08-20)
------------------

**Fixed**

* `Io::stringify()` given a `Throwable` rendered its `__toString()` — message, file, line
  and the full stack trace — into `value()`'s aligned column. It now renders the class and
  the message, the way `attempt()` does
* `Io::stringify()` returns a single line for every value. A line break, carriage return or
  tab in a string, in a `Stringable`, or in a throwable's message is shown as an escape
* `Io::value()` given a label of 14 characters or more emitted no separator between the
  label and the value. Shorter labels are padded as before

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
