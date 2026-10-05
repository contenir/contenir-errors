# Upgrading from 0.x to 2.0

2.0 has the same public API as 0.1. Only the platform requirement changes.

| | 0.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| `contenir/config` | suggested, ^0.1 | **required**, ^0.2 or ^2.0 |

`contenir/config` is now a hard dependency. `Repository\FileRepository` always
needed it at runtime, but 0.x only suggested it, so installing without it
failed on first write. Composer now installs it for you; if you already
require it yourself, keep the constraint compatible with `^0.2 || ^2.0`.

To upgrade, update the constraint:

```bash
composer require contenir/errors:^2.0
```

No code changes are needed. `ErrorPage`, `ErrorPageRepositoryInterface`,
`Repository\FileRepository` and `Repository\InMemoryRepository` keep their
signatures and file format.

## Behaviour change

A hand-edited `title` or `body` that is not a scalar (for example an array)
used to be cast with `(string)`, raising an "Array to string conversion"
warning and reading as `'Array'`. It now reads as `''`:

```php
// errors.local.php
return ['errors' => ['pages' => [404 => ['title' => ['oops'], 'body' => '']]]];

// 0.x: warning, then
$repo->get(404)->title; // 'Array'

// 2.0
$repo->get(404)->title; // ''
```

Scalar values (`404`, `1.5`, `true`) are still cast to string as before.

Projects that must stay on PHP 8.1 or 8.2 can keep using `^0.1`, which is
maintained on the `0.x` branch.
