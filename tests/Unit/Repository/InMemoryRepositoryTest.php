<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Unit\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('errors')]
final class InMemoryRepositoryTest extends TestCase
{
    public function testGetReturnsNullWhenStatusNotConfigured(): void
    {
        self::assertNull((new InMemoryRepository())->get(404));
    }

    public function testGetReturnsConfiguredPage(): void
    {
        $page = new ErrorPage(404, 'Not found', '<p>Body</p>');
        $repo = new InMemoryRepository([$page]);

        self::assertSame($page, $repo->get(404));
    }

    public function testAllReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], (new InMemoryRepository())->all());
    }

    public function testAllIndexesByStatusCode(): void
    {
        $a    = new ErrorPage(404, 'A', '');
        $b    = new ErrorPage(500, 'B', '');
        $repo = new InMemoryRepository([$a, $b]);

        self::assertSame([404 => $a, 500 => $b], $repo->all());
    }

    public function testSaveReplacesExistingPageForSameStatus(): void
    {
        $repo  = new InMemoryRepository([new ErrorPage(404, 'Old', '')]);
        $fresh = new ErrorPage(404, 'New', '');
        $repo->save($fresh);

        self::assertSame($fresh, $repo->get(404));
    }

    public function testSaveAddsNewStatus(): void
    {
        $repo = new InMemoryRepository();
        $page = new ErrorPage(403, 'Forbidden', '');
        $repo->save($page);

        self::assertSame($page, $repo->get(403));
    }

    public function testDeleteRemovesConfiguredPage(): void
    {
        $repo = new InMemoryRepository([new ErrorPage(404, 'x', '')]);
        $repo->delete(404);

        self::assertNull($repo->get(404));
    }

    public function testDeleteIsNoopForUnknownStatus(): void
    {
        $repo = new InMemoryRepository([new ErrorPage(404, 'x', '')]);
        $repo->delete(500);

        self::assertNotNull($repo->get(404));
    }
}
