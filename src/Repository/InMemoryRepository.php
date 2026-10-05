<?php

declare(strict_types=1);

namespace Contenir\Errors\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use Override;

/**
 * Test-friendly repository that holds pages in memory. Shipped in src/ so
 * consumers' tests can require contenir/errors and use this directly without
 * depending on autoload-dev.
 *
 * @api
 */
final class InMemoryRepository implements ErrorPageRepositoryInterface
{
    /**
     * @var array<int, ErrorPage>
     */
    private array $pages = [];

    /**
     * @param array<array-key, ErrorPage> $initial Re-indexed by each page's status code.
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $page) {
            $this->pages[$page->status] = $page;
        }
    }

    /**
     * @return array<int, ErrorPage>
     */
    #[Override]
    public function all(): array
    {
        return $this->pages;
    }

    #[Override]
    public function delete(int $status): void
    {
        unset($this->pages[$status]);
    }

    #[Override]
    public function get(int $status): ?ErrorPage
    {
        return $this->pages[$status] ?? null;
    }

    #[Override]
    public function save(ErrorPage $page): void
    {
        $this->pages[$page->status] = $page;
    }
}
