<?php

declare(strict_types=1);

namespace Contenir\Errors;

use RuntimeException;

/**
 * Persists and retrieves admin-authored error-page content keyed by HTTP status.
 *
 * Implementations should treat a missing/unreadable backing store as "no
 * configured pages" rather than throwing — first-run and permission edge cases
 * are part of normal operation. Errors writing the state, by contrast, must
 * throw so the admin UI can surface them.
 *
 * @api
 */
interface ErrorPageRepositoryInterface
{
    /**
     * @return array<int, ErrorPage> Indexed by status code.
     */
    public function all(): array;

    /**
     * @throws RuntimeException If the page cannot be removed.
     */
    public function delete(int $status): void;

    public function get(int $status): ?ErrorPage;

    /**
     * @throws RuntimeException If the page cannot be persisted.
     */
    public function save(ErrorPage $page): void;
}
