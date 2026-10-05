# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

The public API is unchanged. The major version marks the move to PHP 8.3+
and the php-db QA toolchain shared by all Contenir 2.x packages. See
[UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- Requires PHP 8.3, 8.4 or 8.5. PHP 8.1 and 8.2 are no longer supported.
- `contenir/config` (`^0.2 || ^2.0`) is now a required dependency instead of a
  suggestion. `Repository\FileRepository` cannot work without it.
- `ErrorPage` and `Repository\FileRepository` are declared `readonly` classes.
  Both were already `final` with only readonly properties, so nothing that
  compiled before stops compiling.

### Fixed

- `Repository\FileRepository` no longer raises "Array to string conversion"
  (or throws for an object) when a hand-edited `title` or `body` holds a
  non-scalar value. Such a field now reads as an empty string.
- The README described a flat, status-keyed file format. The file has been
  namespaced under `errors.pages` since 0.1.1; the README now says so.

### Added

- Continuous integration on PHP 8.3, 8.4 and 8.5 against lowest, locked and
  latest dependencies, with coverage reported to Codecov.
- Separate unit (no I/O) and integration (real filesystem) test suites, with
  100% line and branch coverage.

### Removed

- `squizlabs/php_codesniffer` and `phpcs.xml`, replaced by Mago via
  `php-db/phpdb-qa-tools`.

## [0.1.1]

- `FileRepository` namespaces its data under `errors.pages` and merges on
  write through `contenir/config`, preserving every other key in the file.

## [0.1.0]

- Initial release: `ErrorPage` value, `ErrorPageRepositoryInterface`, and
  file-based and in-memory repositories.
