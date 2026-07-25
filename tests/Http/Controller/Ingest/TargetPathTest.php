<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller\Ingest;

use App\Http\BadRequest;
use App\Http\Controller\Ingest\TargetPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TargetPath::class)]
#[CoversClass(BadRequest::class)]
final class TargetPathTest extends TestCase
{
    public function testResolvesFriendsPage(): void
    {
        self::assertSame(
            '/data/friends/stalker.html',
            TargetPath::resolve('/data', 'friends', 'stalker'),
        );
    }

    public function testResolvesActivityPage(): void
    {
        self::assertSame(
            '/data/friends_activity/lenka_penka.html',
            TargetPath::resolve('/data', 'activity', 'lenka_penka'),
        );
    }

    public function testResolvesListPageIgnoringName(): void
    {
        self::assertSame(
            '/data/list.html',
            TargetPath::resolve('/data', 'list', ''),
        );
    }

    public function testStripsTrailingSlashFromDataDir(): void
    {
        self::assertSame(
            '/data/friends/drive-2011.html',
            TargetPath::resolve('/data/', 'friends', 'drive-2011'),
        );
    }

    #[DataProvider('rejectedInputs')]
    public function testRejectsUnsafeInput(string $type, string $name): void
    {
        $this->expectException(BadRequest::class);

        TargetPath::resolve('/data', $type, $name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedInputs(): iterable
    {
        yield 'parent traversal' => ['friends', '..'];
        yield 'nested traversal' => ['activity', '../../etc/passwd'];
        yield 'slash in name' => ['friends', 'a/b'];
        yield 'dot in name' => ['friends', 'a.b'];
        yield 'empty name' => ['friends', ''];
        yield 'uppercase name' => ['activity', 'Wollkey'];
        yield 'unknown type' => ['posters', 'stalker'];
    }
}
