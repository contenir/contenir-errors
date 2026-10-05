<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Integration\Repository;

use Contenir\Config\Exception\WriteException;
use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\FileRepository;
use Contenir\Errors\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function var_export;

#[CoversClass(FileRepository::class)]
#[Group('integration')]
#[Group('repository')]
final class FileRepositoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string, string}>
     */
    public static function fieldValueProvider(): array
    {
        return [
            'missing' => ['', ''],
            'null'    => ['null', ''],
            'integer' => ['404', '404'],
            'float'   => ['1.5', '1.5'],
            'true'    => ['true', '1'],
            'false'   => ['false', ''],
            'array'   => ["['nested']", ''],
            'object'  => ['new \stdClass()', ''],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noPagesProvider(): array
    {
        return [
            'file returns a string'     => ["<?php\n\nreturn 'not an array';\n"],
            'file does not parse'       => ["<?php\n\nthis is not valid php\n"],
            'namespace key missing'     => ["<?php\n\nreturn ['somethingelse' => ['stuff' => 'here']];\n"],
            'namespace is not an array' => ["<?php\n\nreturn ['errors' => 'oops'];\n"],
            'pages key missing'         => ["<?php\n\nreturn ['errors' => ['file' => '/tmp/x']];\n"],
            'pages is not an array'     => ["<?php\n\nreturn ['errors' => ['pages' => 'oops']];\n"],
            'row has a non-integer key' => [
                "<?php\n\nreturn ['errors' => ['pages' => ['oops' => ['title' => 'x']]]];\n",
            ],
            'row is not an array'       => ["<?php\n\nreturn ['errors' => ['pages' => [404 => 'plain string']]];\n"],
        ];
    }

    #[Test]
    #[DataProvider('noPagesProvider')]
    public function allIsEmptyWhenFileHoldsNoUsablePages(string $contents): void
    {
        file_put_contents($this->path(), data: $contents);

        static::assertSame([], (new FileRepository($this->path()))->all());
    }

    #[Test]
    public function allIsEmptyWhenFileIsMissing(): void
    {
        static::assertSame([], (new FileRepository($this->path()))->all());
    }

    #[Test]
    public function allReadsConfiguredPagesIndexedByStatus(): void
    {
        $this->writeConfig([
            'errors' => [
                'pages' => [
                    404 => ['title' => 'Not found', 'body' => '<p>Lost.</p>'],
                    500 => ['title' => 'Oops', 'body' => ''],
                ],
            ],
        ]);

        static::assertEquals(
            [404 => new ErrorPage(404, 'Not found', '<p>Lost.</p>'), 500 => new ErrorPage(500, 'Oops', '')],
            (new FileRepository($this->path()))->all(),
        );
    }

    #[Test]
    #[DataProvider('fieldValueProvider')]
    public function allReadsHandEditedFieldsAsStrings(string $phpValue, string $expected): void
    {
        $field = '' === $phpValue ? '' : "'title' => {$phpValue}, 'body' => {$phpValue}";
        file_put_contents($this->path(), data: "<?php\n\nreturn ['errors' => ['pages' => [404 => [{$field}]]]];\n");

        static::assertEquals(
            [404 => new ErrorPage(404, $expected, $expected)],
            (new FileRepository($this->path()))->all(),
        );
    }

    #[Test]
    public function deleteDoesNotCreateAMissingFile(): void
    {
        (new FileRepository($this->path()))->delete(404);

        static::assertFileDoesNotExist($this->path());
    }

    #[Test]
    public function deleteLeavesTheFileUntouchedWhenStatusIsNotConfigured(): void
    {
        $contents = "<?php return ['errors' => ['pages' => [500 => ['title' => 'b', 'body' => '']]]];\n";
        file_put_contents($this->path(), data: $contents);

        (new FileRepository($this->path()))->delete(404);

        static::assertSame($contents, file_get_contents($this->path()));
    }

    #[Test]
    public function deleteRemovesOnlyThatStatusAndKeepsUnmanagedKeys(): void
    {
        $this->writeConfig(['pagecache' => ['options' => ['cache' => true]]]);
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'a', ''));
        $repo->save(new ErrorPage(500, 'b', ''));

        $repo->delete(404);

        static::assertSame(
            [
                'pagecache' => ['options' => ['cache' => true]],
                'errors'    => ['pages' => [500 => ['title' => 'b', 'body' => '']]],
            ],
            include $this->path(),
        );
    }

    #[Test]
    public function deletingTheLastPageLeavesAnEmptyPagesList(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'a', ''));

        $repo->delete(404);

        static::assertSame(['errors' => ['pages' => []]], include $this->path());
    }

    #[Test]
    public function getReturnsNullWhenStatusIsNotConfigured(): void
    {
        static::assertNull((new FileRepository($this->path()))->get(404));
    }

    #[Test]
    public function saveCreatesMissingParentDirectories(): void
    {
        $nested = $this->path('nested/inner/errors.local.php');

        (new FileRepository($nested))->save(new ErrorPage(404, 'x', ''));

        static::assertEquals(new ErrorPage(404, 'x', ''), (new FileRepository($nested))->get(404));
    }

    #[Test]
    public function saveKeepsOtherStatusesSortedByStatus(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(500, 'Server error', ''));
        $repo->save(new ErrorPage(404, 'Not found', ''));

        /** @var array{errors: array{pages: array<int, mixed>}} $written */
        $written = include $this->path();

        static::assertSame([404, 500], array_keys($written['errors']['pages']));
    }

    #[Test]
    public function saveOverwritesTheExistingPageForAStatus(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new ErrorPage(404, 'Old', ''));

        $repo->save(new ErrorPage(404, 'New', '<p>fresh</p>'));

        static::assertEquals([404 => new ErrorPage(404, 'New', '<p>fresh</p>')], $repo->all());
    }

    #[Test]
    public function savePreservesSiblingKeysWithinTheErrorsNamespace(): void
    {
        $this->writeConfig([
            'errors' => [
                'file'          => '/some/path.php',
                'view_template' => 'app/error/fault',
                'logger'        => 'log.psr3',
            ],
        ]);

        (new FileRepository($this->path()))->save(new ErrorPage(404, 'Lost', ''));

        static::assertSame(
            [
                'errors' => [
                    'file'          => '/some/path.php',
                    'view_template' => 'app/error/fault',
                    'logger'        => 'log.psr3',
                    'pages'         => [404 => ['title' => 'Lost', 'body' => '']],
                ],
            ],
            include $this->path(),
        );
    }

    #[Test]
    public function savePreservesUnmanagedTopLevelKeys(): void
    {
        $this->writeConfig([
            'pagecache'   => ['options' => ['cache' => false]],
            'maintenance' => ['state' => ['active' => true]],
        ]);

        (new FileRepository($this->path()))->save(new ErrorPage(404, 'Lost', ''));

        static::assertSame(
            [
                'pagecache'   => ['options' => ['cache' => false]],
                'maintenance' => ['state' => ['active' => true]],
                'errors'      => ['pages' => [404 => ['title' => 'Lost', 'body' => '']]],
            ],
            include $this->path(),
        );
    }

    #[Test]
    public function saveReplacesANamespaceThatIsNotAnArray(): void
    {
        $this->writeConfig(['errors' => 'oops']);

        (new FileRepository($this->path()))->save(new ErrorPage(404, 'Lost', ''));

        static::assertSame(
            ['errors' => ['pages' => [404 => ['title' => 'Lost', 'body' => '']]]],
            include $this->path(),
        );
    }

    #[Test]
    public function saveRoundTripsQuotesAndNewlinesAcrossInstances(): void
    {
        $page = new ErrorPage(404, "Tom's page", "<p>O'Brien said: \"don't\"</p>\n<p>Multi-line.</p>");

        (new FileRepository($this->path()))->save($page);

        static::assertEquals($page, (new FileRepository($this->path()))->get(404));
    }

    #[Test]
    public function saveThrowsWhenDestinationIsADirectory(): void
    {
        mkdir($this->path(), permissions: 0o755);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('Cannot install error pages at');

        (new FileRepository($this->path()))->save(new ErrorPage(404, 'x', ''));
    }

    #[Test]
    public function saveThrowsWhenParentDirectoryCannotBeCreated(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('Cannot create error pages directory');

        (new FileRepository($this->path('locked/nested/errors.local.php')))->save(new ErrorPage(404, 'x', ''));
    }

    #[Test]
    public function saveThrowsWhenParentDirectoryIsNotWritable(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('Cannot write error pages to');

        (new FileRepository($this->path('locked/errors.local.php')))->save(new ErrorPage(404, 'x', ''));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function writeConfig(array $config): void
    {
        file_put_contents(
            $this->path(),
            data: "<?php\n\nreturn "
                . var_export(
                    value: $config,
                    return: true,
                )
                . ";\n",
        );
    }
}
