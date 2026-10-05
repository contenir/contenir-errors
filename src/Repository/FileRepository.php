<?php

declare(strict_types=1);

namespace Contenir\Errors\Repository;

use Contenir\Config\Exception\WriteException;
use Contenir\Config\Reader\PhpArray as ConfigReader;
use Contenir\Config\Writer\PhpArray as ConfigWriter;
use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use Override;

use function array_key_exists;
use function array_keys;
use function array_map;
use function is_array;
use function is_int;
use function is_scalar;
use function ksort;

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
 *
 * @api
 */
final readonly class FileRepository implements ErrorPageRepositoryInterface
{
    private const string NAMESPACE_KEY = 'errors';
    private const string PAGES_KEY     = 'pages';
    private const string WRITE_LABEL   = 'error pages';

    public function __construct(
        private string $filePath,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * Scalars are cast as before; anything else (a nested array, an object)
     * reads as an empty string rather than raising "Array to string
     * conversion" on every request.
     */
    private static function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<int, ErrorPage>
     */
    #[Override]
    public function all(): array
    {
        $config = ConfigReader::fromFile($this->filePath);
        $rows   = self::arrayOrEmpty(self::arrayOrEmpty($config[self::NAMESPACE_KEY] ?? null)[self::PAGES_KEY] ?? null);

        $pages = [];
        foreach (array_map(
            static fn(int|string $status, mixed $row): ?ErrorPage => is_int($status) && is_array($row)
                ? new ErrorPage(
                    $status,
                    self::scalarString($row['title'] ?? ''),
                    self::scalarString($row['body'] ?? ''),
                )
                : null,
            array_keys($rows),
            $rows,
        ) as $page) {
            if (null === $page) {
                continue;
            }

            $pages[$page->status] = $page;
        }

        return $pages;
    }

    /**
     * @throws WriteException If the file cannot be written.
     */
    #[Override]
    public function delete(int $status): void
    {
        $pages = $this->all();
        if (! array_key_exists($status, $pages)) {
            return;
        }

        unset($pages[$status]);
        $this->persistPages($pages);
    }

    #[Override]
    public function get(int $status): ?ErrorPage
    {
        return $this->all()[$status] ?? null;
    }

    /**
     * @throws WriteException If the file cannot be written.
     */
    #[Override]
    public function save(ErrorPage $page): void
    {
        $pages                = $this->all();
        $pages[$page->status] = $page;

        $this->persistPages($pages);
    }

    /**
     * @param array<int, ErrorPage> $pages
     *
     * @throws WriteException If the file cannot be written.
     */
    private function persistPages(array $pages): void
    {
        ksort($pages);

        $payload = [];
        foreach ($pages as $status => $page) {
            $payload[$status] = ['title' => $page->title, 'body' => $page->body];
        }

        $config                      = ConfigReader::fromFile($this->filePath);
        $namespace                   = self::arrayOrEmpty($config[self::NAMESPACE_KEY] ?? null);
        $namespace[self::PAGES_KEY]  = $payload;
        $config[self::NAMESPACE_KEY] = $namespace;

        ConfigWriter::toFile($this->filePath, $config, self::WRITE_LABEL);
    }
}
