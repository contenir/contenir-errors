<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Unit\Repository;

use Contenir\Errors\ErrorPage;
use Contenir\Errors\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRepository::class)]
#[Group('unit')]
#[Group('errors')]
final class InMemoryRepositoryTest extends TestCase
{
    #[Test]
    public function allIndexesInitialPagesByStatusCode(): void
    {
        $notFound = new ErrorPage(404, 'A', '');
        $broken   = new ErrorPage(500, 'B', '');

        static::assertSame([404 => $notFound, 500 => $broken], (new InMemoryRepository([$notFound, $broken]))->all());
    }

    #[Test]
    public function allIsEmptyByDefault(): void
    {
        static::assertSame([], (new InMemoryRepository())->all());
    }

    #[Test]
    public function deleteIgnoresAnUnknownStatus(): void
    {
        $page = new ErrorPage(404, 'x', '');
        $repo = new InMemoryRepository([$page]);

        $repo->delete(500);

        static::assertSame([404 => $page], $repo->all());
    }

    #[Test]
    public function deleteRemovesTheConfiguredPage(): void
    {
        $repo = new InMemoryRepository([new ErrorPage(404, 'x', '')]);

        $repo->delete(404);

        static::assertNull($repo->get(404));
    }

    #[Test]
    public function getReturnsNullWhenStatusIsNotConfigured(): void
    {
        static::assertNull((new InMemoryRepository())->get(404));
    }

    #[Test]
    public function getReturnsTheConfiguredPage(): void
    {
        $page = new ErrorPage(404, 'Not found', '<p>Body</p>');

        static::assertSame($page, (new InMemoryRepository([$page]))->get(404));
    }

    #[Test]
    public function saveAddsANewStatus(): void
    {
        $repo = new InMemoryRepository();
        $page = new ErrorPage(403, 'Forbidden', '');

        $repo->save($page);

        static::assertSame($page, $repo->get(403));
    }

    #[Test]
    public function saveReplacesThePageForTheSameStatus(): void
    {
        $repo  = new InMemoryRepository([new ErrorPage(404, 'Old', '')]);
        $fresh = new ErrorPage(404, 'New', '');

        $repo->save($fresh);

        static::assertSame([404 => $fresh], $repo->all());
    }
}
