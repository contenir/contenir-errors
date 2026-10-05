# contenir/errors

[![Continuous Integration](https://github.com/contenir/errors/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/errors/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/errors/graph/badge.svg)](https://codecov.io/gh/contenir/errors)

Framework-agnostic admin-authored error-page content for [Contenir CMS](https://github.com/contenir).

The CMS lets an operator author per-status error pages (404, 500, 403,
…). The consuming Site (Mezzio, Laminas MVC, anything else) reads those
pages on every error and replaces the framework's default rendering with
on-brand content.

This package provides the *domain*: an immutable per-status value plus
a repository interface, with file-based and in-memory implementations.
Framework-specific listeners and middleware come from sibling packages
(e.g. [`contenir/errors-laminas-mvc`](https://github.com/contenir/errors-laminas-mvc)).

## Requirements

- PHP 8.3, 8.4 or 8.5
- [`contenir/config`](https://github.com/contenir/config) `^0.2 || ^2.0`, only if
  you use `Repository\FileRepository`

The 0.x releases, which support PHP 8.1, remain available from the `0.x`
branch and `v0.*` tags; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Installation

```bash
composer require contenir/errors
# and, for the admin-side file writer:
composer require contenir/config
```

The package has no runtime dependencies of its own. `contenir/config` is a
suggestion because a Site that reads pages from its merged Laminas/Mezzio
config and uses `InMemoryRepository` never touches the file.

## Usage

### `ErrorPage`

An immutable (`readonly`) value for one HTTP status:

```php
use Contenir\Errors\ErrorPage;

$page = new ErrorPage(404, 'Page not found', '<p>Try the homepage.</p>');

$page->status;    // 404
$page->title;     // 'Page not found'
$page->body;      // '<p>Try the homepage.</p>'
$page->isEmpty(); // false; true only when title and body are both ''
```

Listeners treat an empty page as absent and leave the framework's default
rendering alone.

The body is expected to be a *sanitized* HTML fragment: inline elements
only, no scripts or styles. Sanitization is the writer's responsibility;
readers render it raw and trust the contract.

### `ErrorPageRepositoryInterface`

| Method | Returns | Notes |
| --- | --- | --- |
| `get(int $status)` | `?ErrorPage` | `null` when the status has no page |
| `all()` | `array<int, ErrorPage>` | indexed by status code |
| `save(ErrorPage $page)` | `void` | replaces any page for the same status; throws `RuntimeException` on failure |
| `delete(int $status)` | `void` | a no-op for an unknown status; throws `RuntimeException` on failure |

Implementations treat a missing or unreadable backing store as "no
configured pages" rather than throwing, so first-run and permission edge
cases never crash a Site. Write failures do throw, so the admin UI can
report them.

### `Repository\FileRepository`

Reads and writes a PHP-array config file:

```php
use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\FileRepository;

$repo = new FileRepository('/var/www/shared/config/autoload/errors.local.php');

$page = $repo->get(404);
if ($page !== null && ! $page->isEmpty()) {
    // render $page->title and $page->body
}

$repo->save(new ErrorPage(404, 'Page not found', '<p>Try the homepage.</p>'));
$repo->delete(404);
```

The file follows the Laminas/Mezzio config-namespacing convention, so it
can sit in `config/autoload/` and be merged into the application config:

```php
<?php

return [
    'errors' => [
        'pages' => [
            404 => [
                'title' => 'Page not found',
                'body'  => '<p>Try the <a href="/">homepage</a>.</p>',
            ],
            500 => [
                'title' => 'Something went wrong',
                'body'  => '<p>We have been notified.</p>',
            ],
        ],
    ],
];
```

- The repository owns only `errors.pages`. Every other top-level key, and
  any sibling key under `errors` (`file`, `view_template`, `logger`, …), is
  preserved on save, so operators can hand-edit the same file.
- Pages are written sorted by status code.
- Writes are atomic (temporary file and rename) and invalidate the file's
  opcache entry, via `contenir/config`. A failed write throws
  `Contenir\Config\Exception\WriteException`, a `RuntimeException`.
- A missing, unreadable or unparsable file reads as no pages. Rows whose
  key is not an integer, or whose value is not an array, are skipped. A
  missing or non-scalar `title`/`body` reads as `''`; other scalars are
  cast to string.

### `Repository\InMemoryRepository`

Holds pages in memory. It ships in `src/` so consumers can use it in their
own tests, and so an adapter can build a repository from merged config:

```php
use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\InMemoryRepository;

$repo = new InMemoryRepository([
    new ErrorPage(404, 'Not found', '<p>Lost.</p>'),
]);
```

The constructor re-indexes the pages it is given by their `status`.

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: value object and in-memory repository, no I/O
composer test-integration  # integration suite: FileRepository against a real temp directory
composer test-coverage     # both suites, clover.xml for Codecov
```

## License

MIT. See [LICENSE](LICENSE).
