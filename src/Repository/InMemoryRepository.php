<?php

declare(strict_types=1);

namespace Contenir\Errors\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;

/**
 * Test-friendly repository that holds pages in memory. Shipped in src/ so
 * consumers' tests can require contenir/errors and use this directly without
 * depending on autoload-dev.
 */
final class InMemoryRepository implements ErrorPageRepositoryInterface
{
    /**
     * @var array<int, ErrorPage>
     */
    private array $pages = [];

    /**
     * @param array<int, ErrorPage> $initial Indexed by status code.
     */
    public function __construct(array $initial = [])
    {
        foreach ($initial as $page) {
            $this->pages[$page->status] = $page;
        }
    }

    public function get(int $status): ?ErrorPage
    {
        return $this->pages[$status] ?? null;
    }

    public function all(): array
    {
        return $this->pages;
    }

    public function save(ErrorPage $page): void
    {
        $this->pages[$page->status] = $page;
    }

    public function delete(int $status): void
    {
        unset($this->pages[$status]);
    }
}
