# contenir/errors

Framework-agnostic admin-authored error-page content for [Contenir CMS](https://github.com/contenir).

The CMS lets an operator author per-status error pages (404, 500, 403,
…). The consuming Site (Mezzio, Laminas MVC, anything else) reads those
pages on every error and replaces the framework's default rendering with
on-brand content.

This package provides the *domain* — an immutable per-status value plus
a repository interface, with file-based and in-memory implementations.
Framework-specific listeners/middleware come from sibling packages
(e.g. `contenir/errors-laminas-mvc`).

## Install

```bash
composer require contenir/errors
```

Zero runtime dependencies — pure PHP 8.1+.

## Usage

### Reading pages

```php
use Contenir\Errors\Repository\FileRepository;

$repo = new FileRepository('/var/www/shared/errors.local.php');
$page = $repo->get(404);

if ($page !== null && ! $page->isEmpty()) {
    // Render with $page->title and $page->body
}
```

### Writing pages (admin)

```php
use Contenir\Errors\ErrorPage;

$repo->save(new ErrorPage(404, 'Page not found', '<p>Try the homepage.</p>'));
$repo->delete(404);
```

The body is expected to be a *sanitized* HTML fragment — inline elements
only, no scripts/styles. Sanitization is the writer's responsibility;
readers render raw and trust the contract.

### File format

`FileRepository` reads/writes a PHP file that returns an array indexed
by HTTP status code:

```php
<?php

return [
    404 => [
        'title' => 'Page not found',
        'body'  => '<p>Try the <a href="/">homepage</a>.</p>',
    ],
    500 => [
        'title' => 'Something went wrong',
        'body'  => '<p>We have been notified.</p>',
    ],
];
```

A missing or unreadable file resolves to no configured pages, so
first-run and permission edge cases don't crash the consumer.

### Testing

`InMemoryRepository` is shipped in `src/` so consumers can use it in
their own test suites:

```php
use Contenir\Errors\Repository\InMemoryRepository;
use Contenir\Errors\ErrorPage;

$repo = new InMemoryRepository([
    new ErrorPage(404, 'Not found', '<p>Lost.</p>'),
]);
```

## License

MIT