<?php

declare(strict_types=1);

namespace Contenir\Errors\Tests\Unit;

use Contenir\Errors\ErrorPage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('errors')]
final class ErrorPageTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        $page = new ErrorPage(404, 'Not found', '<p>Try the homepage.</p>');

        self::assertSame(404, $page->status);
        self::assertSame('Not found', $page->title);
        self::assertSame('<p>Try the homepage.</p>', $page->body);
    }

    public function testIsEmptyWhenTitleAndBodyAreBlank(): void
    {
        self::assertTrue((new ErrorPage(404, '', ''))->isEmpty());
    }

    public function testIsNotEmptyWhenTitleSet(): void
    {
        self::assertFalse((new ErrorPage(404, 'Not found', ''))->isEmpty());
    }

    public function testIsNotEmptyWhenBodySet(): void
    {
        self::assertFalse((new ErrorPage(404, '', '<p>Body only.</p>'))->isEmpty());
    }

    public function testStateIsImmutable(): void
    {
        $page       = new ErrorPage(500, 'Oops', '<p>Body</p>');
        $reflection = new \ReflectionClass($page);

        foreach ($reflection->getProperties() as $property) {
            self::assertTrue($property->isReadOnly(), sprintf('Property %s should be readonly', $property->getName()));
        }
    }
}
