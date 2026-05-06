<?php

declare(strict_types=1);

namespace Contenir\Errors\Repository;

use Contenir\Config\Reader\PhpArray as ConfigReader;
use Contenir\Config\Writer\PhpArray as ConfigWriter;
use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;

/**
 * PHP-array file backing store.
 *
 * The file follows the Laminas/Mezzio config-namespacing convention:
 *
 *     return [
 *         'errors' => [
 *             'pages' => [
 *                 403 => ['title' => '...', 'body' => '...'],
 *                 404 => ['title' => '...', 'body' => '...'],
 *             ],
 *         ],
 *     ];
 *
 * The repository owns only the `errors.pages` subkey. All other top-level
 * keys, and any sibling keys under `errors`, are preserved on save —
 * operators or other tooling can hand-edit the same file safely.
 *
 * A missing or unreadable file resolves to "no configured pages" so
 * first-run consumers don't crash before the admin has authored anything.
 */
final class FileRepository implements ErrorPageRepositoryInterface
{
    private const NAMESPACE_KEY = 'errors';
    private const PAGES_KEY     = 'pages';
    private const WRITE_LABEL   = 'error pages';

    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function get(int $status): ?ErrorPage
    {
        return $this->all()[$status] ?? null;
    }

    public function all(): array
    {
        $config    = ConfigReader::fromFile($this->filePath);
        $pagesData = $config[self::NAMESPACE_KEY][self::PAGES_KEY] ?? null;

        if (! is_array($pagesData)) {
            return [];
        }

        $pages = [];
        foreach ($pagesData as $status => $row) {
            if (! is_int($status) || ! is_array($row)) {
                continue;
            }
            $pages[$status] = new ErrorPage(
                $status,
                (string) ($row['title'] ?? ''),
                (string) ($row['body'] ?? ''),
            );
        }

        return $pages;
    }

    public function save(ErrorPage $page): void
    {
        $pages                = $this->all();
        $pages[$page->status] = $page;

        $this->persistPages($pages);
    }

    public function delete(int $status): void
    {
        $pages = $this->all();
        if (! isset($pages[$status])) {
            return;
        }

        unset($pages[$status]);
        $this->persistPages($pages);
    }

    /**
     * @param array<int, ErrorPage> $pages
     */
    private function persistPages(array $pages): void
    {
        ksort($pages);

        $payload = [];
        foreach ($pages as $status => $page) {
            $payload[$status] = [
                'title' => $page->title,
                'body'  => $page->body,
            ];
        }

        $config = ConfigReader::fromFile($this->filePath);
        if (! isset($config[self::NAMESPACE_KEY]) || ! is_array($config[self::NAMESPACE_KEY])) {
            $config[self::NAMESPACE_KEY] = [];
        }
        $config[self::NAMESPACE_KEY][self::PAGES_KEY] = $payload;

        ConfigWriter::toFile($this->filePath, $config, self::WRITE_LABEL);
    }
}
