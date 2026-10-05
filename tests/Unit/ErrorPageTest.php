<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Unit;

use Contenir\Errors\ErrorPage;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorPage::class)]
#[Group('unit')]
#[Group('errors')]
final class ErrorPageTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function emptinessProvider(): array
    {
        return [
            'no title, no body' => ['', '', true],
            'title only'        => ['Not found', '', false],
            'body only'         => ['', '<p>Body only.</p>', false],
            'title and body'    => ['Not found', '<p>Lost.</p>', false],
        ];
    }

    #[Test]
    public function exposesStatusTitleAndBody(): void
    {
        $page = new ErrorPage(404, 'Not found', '<p>Try the homepage.</p>');

        static::assertSame([404, 'Not found', '<p>Try the homepage.</p>'], [$page->status, $page->title, $page->body]);
    }

    #[Test]
    #[DataProvider('emptinessProvider')]
    public function isEmptyOnlyWhenTitleAndBodyAreBothBlank(string $title, string $body, bool $expected): void
    {
        static::assertSame($expected, (new ErrorPage(404, $title, $body))->isEmpty());
    }

    #[Test]
    public function rejectsModificationAfterConstruction(): void
    {
        $page = new ErrorPage(500, 'Oops', '<p>Body</p>');

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $page->title = 'Changed';
    }
}
