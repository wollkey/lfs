<?php

declare(strict_types=1);

namespace App\Tests\Telegram\Inbox;

use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\MessageKind;
use App\Telegram\Inbox\MessageLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageLog::class)]
final class MessageLogTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/lfs-inbox-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testAppendsOneLinePerMessageIntoAMonthlyFile(): void
    {
        $log = new MessageLog($this->dir);

        $log->append($this->message(1, 'Первый отзыв'));
        $log->append($this->message(2, 'Второй отзыв'));

        $file = $this->dir.'/updates-2026-09.jsonl';
        self::assertFileExists($file);

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        self::assertIsArray($first);
        self::assertSame('message', $first['kind']);
        self::assertSame('Первый отзыв', $first['text']);
    }

    public function testKeepsMonthsApart(): void
    {
        $log = new MessageLog($this->dir);

        $log->append($this->message(1, 'Сентябрьский отзыв'));
        $log->append($this->message(2, 'Октябрьский отзыв', 1_791_201_600));

        self::assertFileExists($this->dir.'/updates-2026-09.jsonl');
        self::assertFileExists($this->dir.'/updates-2026-10.jsonl');
    }

    private function message(int $updateId, string $text, int $date = 1_789_041_600): CapturedMessage
    {
        return new CapturedMessage(
            MessageKind::Message,
            $updateId,
            -1001234567890,
            $updateId * 10,
            $date,
            4242,
            'christallisme',
            $text,
        );
    }
}
