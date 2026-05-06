<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Unit\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\FileRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
#[Group('errors')]
final class FileRepositoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/contenir-errors-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->purge($this->tmpDir);
        }
        parent::tearDown();
    }

    private function purge(string $dir): void
    {
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->purge($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }

    private function path(string $name = 'errors.local.php'): string
    {
        return $this->tmpDir . '/' . $name;
    }

    private function writeConfig(array $config): void
    {
        file_put_contents(
            $this->path(),
            "<?php\n\nreturn " . var_export($config, true) . ";\n",
        );
    }

    public function testAllReturnsEmptyWhenFileMissing(): void
    {
        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testGetReturnsNullWhenFileMissing(): void
    {
        self::assertNull((new FileRepository($this->path()))->get(404));
    }

    public function testAllReturnsEmptyWhenFileContentsAreNotAnArray(): void
    {
        file_put_contents($this->path(), "<?php\n\nreturn 'not an array';\n");

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllReturnsEmptyWhenNamespaceKeyMissing(): void
    {
        $this->writeConfig(['somethingelse' => ['stuff' => 'here']]);

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllReturnsEmptyWhenPagesKeyMissing(): void
    {
        $this->writeConfig(['errors' => ['file' => '/tmp/x']]);

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllReturnsEmptyWhenPagesKeyIsNotArray(): void
    {
        $this->writeConfig(['errors' => ['pages' => 'oops']]);

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllSkipsRowsWithNonIntegerKeys(): void
    {
        $this->writeConfig([
            'errors' => ['pages' => ['oops' => ['title' => 'x', 'body' => '']]],
        ]);

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllSkipsRowsThatAreNotArrays(): void
    {
        $this->writeConfig([
            'errors' => ['pages' => [404 => 'plain string']],
        ]);

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testAllReadsConfiguredPagesIndexedByStatus(): void
    {
        $this->writeConfig([
            'errors' => [
                'pages' => [
                    404 => ['title' => 'Not found', 'body' => '<p>Lost.</p>'],
                    500 => ['title' => 'Oops', 'body' => ''],
                ],
            ],
        ]);

        $pages = (new FileRepository($this->path()))->all();

        self::assertCount(2, $pages);
        self::assertSame('Not found', $pages[404]->title);
        self::assertSame('<p>Lost.</p>', $pages[404]->body);
        self::assertSame('Oops', $pages[500]->title);
        self::assertSame('', $pages[500]->body);
    }

    public function testAllDefaultsMissingFieldsToEmptyString(): void
    {
        $this->writeConfig(['errors' => ['pages' => [404 => []]]]);

        $page = (new FileRepository($this->path()))->get(404);

        self::assertNotNull($page);
        self::assertSame('', $page->title);
        self::assertSame('', $page->body);
    }

    public function testSaveAndGetRoundTripsAcrossInstances(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Not found', '<p>Try home.</p>'));

        $loaded = (new FileRepository($this->path()))->get(404);

        self::assertNotNull($loaded);
        self::assertSame(404, $loaded->status);
        self::assertSame('Not found', $loaded->title);
        self::assertSame('<p>Try home.</p>', $loaded->body);
    }

    public function testSavePreservesOtherStatuses(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Not found', ''));
        $repo->save(new ErrorPage(500, 'Server error', ''));

        $all = $repo->all();
        self::assertArrayHasKey(404, $all);
        self::assertArrayHasKey(500, $all);
    }

    public function testSaveOverwritesExistingStatus(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Old', ''));
        $repo->save(new ErrorPage(404, 'New', '<p>fresh</p>'));

        $page = $repo->get(404);
        self::assertNotNull($page);
        self::assertSame('New', $page->title);
        self::assertSame('<p>fresh</p>', $page->body);
    }

    public function testSavePersistsValuesContainingQuotesAndNewlines(): void
    {
        $body = "<p>O'Brien said: \"don't\"</p>\n<p>Multi-line.</p>";
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, "Tom's page", $body));

        $loaded = (new FileRepository($this->path()))->get(404);

        self::assertNotNull($loaded);
        self::assertSame("Tom's page", $loaded->title);
        self::assertSame($body, $loaded->body);
    }

    public function testSavePreservesUnmanagedTopLevelKeys(): void
    {
        // An operator (or another package) wrote sibling top-level keys to
        // the same file. Saving an error page must leave them untouched.
        $this->writeConfig([
            'pagecache'   => ['options' => ['cache' => false]],
            'maintenance' => ['state' => ['active' => true]],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Lost', ''));

        $reloaded = include $this->path();

        self::assertSame(['options' => ['cache' => false]], $reloaded['pagecache']);
        self::assertSame(['state' => ['active' => true]], $reloaded['maintenance']);
        self::assertSame('Lost', $reloaded['errors']['pages'][404]['title']);
    }

    public function testSavePreservesSiblingKeysWithinErrorsNamespace(): void
    {
        // The .global.php that wires the package may declare other keys
        // under 'errors' (e.g. file, view_template, logger). Save must
        // touch only the 'pages' subkey.
        $this->writeConfig([
            'errors' => [
                'file'          => '/some/path.php',
                'view_template' => 'app/error/fault',
                'logger'        => 'log.psr3',
            ],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Lost', ''));

        $reloaded = include $this->path();

        self::assertSame('/some/path.php', $reloaded['errors']['file']);
        self::assertSame('app/error/fault', $reloaded['errors']['view_template']);
        self::assertSame('log.psr3', $reloaded['errors']['logger']);
        self::assertSame('Lost', $reloaded['errors']['pages'][404]['title']);
    }

    public function testSaveCreatesParentDirectoryIfMissing(): void
    {
        $nested = $this->tmpDir . '/nested/inner/errors.local.php';
        $repo   = new FileRepository($nested);

        $repo->save(new ErrorPage(404, 'x', ''));

        self::assertFileExists($nested);
    }

    public function testSaveIsAtomicViaTempFileRename(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'first', ''));

        self::assertFileDoesNotExist($this->path() . '.tmp');
    }

    public function testSaveThrowsWhenDestinationDirectoryUnwritable(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        $repo = new FileRepository($readOnly . '/errors.local.php');

        try {
            $this->expectException(RuntimeException::class);
            $repo->save(new ErrorPage(404, 'x', ''));
        } finally {
            chmod($readOnly, 0o755);
            rmdir($readOnly);
        }
    }

    public function testDeleteRemovesEntryFromFile(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'a', ''));
        $repo->save(new ErrorPage(500, 'b', ''));

        $repo->delete(404);

        $loaded = (new FileRepository($this->path()))->all();
        self::assertArrayNotHasKey(404, $loaded);
        self::assertArrayHasKey(500, $loaded);
    }

    public function testDeleteIsNoopWhenStatusMissing(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(500, 'b', ''));
        $repo->delete(404);

        $loaded = (new FileRepository($this->path()))->all();
        self::assertArrayHasKey(500, $loaded);
    }

    public function testDeleteIsNoopWhenFileMissing(): void
    {
        $repo = new FileRepository($this->path());
        $repo->delete(404);

        self::assertFileDoesNotExist($this->path());
    }

    public function testDeletePreservesUnmanagedTopLevelKeys(): void
    {
        $this->writeConfig(['pagecache' => ['options' => ['cache' => true]]]);

        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'a', ''));
        $repo->save(new ErrorPage(500, 'b', ''));
        $repo->delete(404);

        $reloaded = include $this->path();
        self::assertSame(['options' => ['cache' => true]], $reloaded['pagecache']);
        self::assertArrayNotHasKey(404, $reloaded['errors']['pages']);
        self::assertArrayHasKey(500, $reloaded['errors']['pages']);
    }

    public function testEmptyAfterDeletingAllStillProducesValidFile(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'a', ''));
        $repo->delete(404);

        $loaded = (new FileRepository($this->path()))->all();
        self::assertSame([], $loaded);
    }

    public function testAllReturnsEmptyWhenIncludeThrows(): void
    {
        // A PHP parse error inside the included file surfaces as a Throwable
        // (ParseError/CompileError). Repository must absorb it and resolve to
        // "no configured pages" rather than crash the request.
        file_put_contents($this->path(), "<?php\n\nthis is not valid php\n");

        self::assertSame([], (new FileRepository($this->path()))->all());
    }

    public function testSaveProducesNamespacedFileFormat(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Lost', '<p>x</p>'));

        $loaded = include $this->path();

        self::assertSame([
            'errors' => [
                'pages' => [
                    404 => ['title' => 'Lost', 'body' => '<p>x</p>'],
                ],
            ],
        ], $loaded);
    }

    public function testSaveThrowsWhenParentDirectoryCannotBeCreated(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        $repo = new FileRepository($readOnly . '/nested/errors.local.php');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Cannot create error pages directory/');
            $repo->save(new ErrorPage(404, 'x', ''));
        } finally {
            chmod($readOnly, 0o755);
            $this->purge($readOnly);
        }
    }

    public function testSaveThrowsWhenDestinationIsDirectory(): void
    {
        // Rename can't overwrite a directory with a file — exercises the
        // post-write atomic-swap failure branch.
        $dest = $this->path();
        mkdir($dest, 0o755, true);

        $repo = new FileRepository($dest);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Cannot install error pages/');
            $repo->save(new ErrorPage(404, 'x', ''));
        } finally {
            // tmp file should have been cleaned up by the failure handler.
            self::assertFileDoesNotExist($dest . '.tmp');
        }
    }
}
