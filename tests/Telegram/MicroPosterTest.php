<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\MicroPoster;
use App\Telegram\Post;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CoversClass(MicroPoster::class)]
final class MicroPosterTest extends TestCase
{
    private string $poster;

    protected function setUp(): void
    {
        $this->poster = (string) tempnam(sys_get_temp_dir(), 'lfs_poster_');
    }

    protected function tearDown(): void
    {
        @unlink($this->poster);
    }

    public function testACaptionThatFitsGoesUnderThePhoto(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->post($client, '<b>Лена</b> - '.str_repeat('я', 1000));

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringStartsWith('<b>Лена</b>', $client->photos[0]['caption']);
        self::assertSame([], $client->texts);
    }

    public function testATooLongCaptionFollowsThePhotoAsItsOwnMessage(): void
    {
        $client = new RecordingTelegramClient();
        $caption = '<b>Лена</b> - '.str_repeat('я', 1100);

        $exit = $this->post($client, $caption);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame('', $client->photos[0]['caption']);
        self::assertSame($caption, $client->texts[0]['html']);
    }

    private function post(RecordingTelegramClient $client, string $caption): int
    {
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());

        return new MicroPoster($client, '-100500')->post($io, new Post('Пост', intro: $caption, images: [$this->poster]), false);
    }
}
