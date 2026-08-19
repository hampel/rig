# Changelog

All notable changes to `hampel/rig` are documented here.

## Unreleased

First release. A harness for exercising a package by hand, with real side effects.

- `vendor/bin/rig` lists and runs exercises from a package's `harness/` directory.
- Exercises are plain PHP scripts, handed one variable: `$io`.
- Each exercise runs in a fresh PHP process by default; `--php` selects the binary,
  `--in-process` opts out.
- `.env` beside the package is loaded into `getenv()` before an exercise runs. Values
  already present in the environment take precedence.
- No dependencies.
