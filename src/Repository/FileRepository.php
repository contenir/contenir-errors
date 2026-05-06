<?php

declare(strict_types=1);

namespace Contenir\Errors\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\ErrorPageRepositoryInterface;
use RuntimeException;
use Throwable;

/**
 * PHP-array file backing store.
 *
 * The file returns an associative array keyed by status code — opcache-cacheable,
 * fast to read on every request. A missing or unreadable file resolves to "no
 * configured pages" so first-run consumers don't crash before the admin has ever
 * authored anything. Save errors throw; the caller (admin UI) is expected to
 * surface them.
 */
final class FileRepository implements ErrorPageRepositoryInterface
{
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
        if (! is_file($this->filePath) || ! is_readable($this->filePath)) {
            return [];
        }

        try {
            /** @psalm-suppress UnresolvableInclude */
            $data = include $this->filePath;
        } catch (Throwable) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $pages = [];
        foreach ($data as $status => $row) {
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
        $pages           = $this->all();
        $pages[$page->status] = $page;

        $this->writeAll($pages);
    }

    public function delete(int $status): void
    {
        $pages = $this->all();
        if (! isset($pages[$status])) {
            return;
        }

        unset($pages[$status]);
        $this->writeAll($pages);
    }

    /**
     * @param array<int, ErrorPage> $pages
     */
    private function writeAll(array $pages): void
    {
        $payload = [];
        ksort($pages);
        foreach ($pages as $status => $page) {
            $payload[$status] = [
                'title' => $page->title,
                'body'  => $page->body,
            ];
        }

        $contents = "<?php\n\nreturn " . self::exportArray($payload) . ";\n";

        $dir = \dirname($this->filePath);
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create errors directory "%s".', $dir));
        }

        $tmp = $this->filePath . '.tmp';
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Cannot write error pages to "%s".', $tmp));
        }

        // Atomic swap so a partial write is never visible to readers.
        if (! @rename($tmp, $this->filePath)) {
            @unlink($tmp);
            throw new RuntimeException(sprintf('Cannot install error pages at "%s".', $this->filePath));
        }

        // Drop any cached opcode for the old contents — otherwise readers in
        // long-running PHP-FPM workers would see stale state.
        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->filePath, true);
        }
    }

    /**
     * @param array<int, array{title: string, body: string}> $data
     */
    private static function exportArray(array $data): string
    {
        if ($data === []) {
            return '[]';
        }

        $lines = ['['];
        foreach ($data as $status => $row) {
            $lines[] = sprintf('    %d => [', $status);
            $lines[] = sprintf('        %s => %s,', var_export('title', true), var_export($row['title'], true));
            $lines[] = sprintf('        %s => %s,', var_export('body', true), var_export($row['body'], true));
            $lines[] = '    ],';
        }
        $lines[] = ']';
        return implode("\n", $lines);
    }
}
