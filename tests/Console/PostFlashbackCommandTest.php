<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostFlashbackCommand;
use App\Telegram\Card\PanelsCard;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Telegram\Rasterizer;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\FakeRasterizer;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostFlashbackCommand::class)]
#[CoversClass(MicroPoster::class)]
final class PostFlashbackCommandTest extends IntegrationTestCase
{
    public function testPostsTheFlashbackCard(): void
    {
        $this->seedFlashback();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->photos);
        self::assertStringContainsString('Год назад', $client->photos[0]['caption']);
    }

    public function testSkipsWhenNothingWatchedThatWeekAYearAgo(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertSame([], $client->sent);
    }

    public function testFallsBackToTheTableWhenRenderingFails(): void
    {
        $this->seedFlashback();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer(fail: true)))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertCount(1, $client->sent);
    }

    public function testReturnsFailureWhenTelegramErrors(): void
    {
        $this->seedFlashback();
        $client = new RecordingTelegramClient(fail: true);

        self::assertSame(Command::FAILURE, $this->tester($this->command($client, new FakeRasterizer()))->execute([]));
    }

    public function testReturnsInvalidWhenUnconfigured(): void
    {
        $this->seedFlashback();
        $command = new PostFlashbackCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            new MicroPoster(new PanelsCard(), new FakeRasterizer(), null, null),
        );

        self::assertSame(Command::INVALID, $this->tester($command)->execute([]));
    }

    private function seedFlashback(): void
    {
        $monday = new \DateTimeImmutable('-1 year')->modify('monday this week')->format('Y-m-d');
        $this->givenMembers('anna', 'boris');
        $this->givenRound(1);
        $this->givenFilmRatedBy('oldie', ['anna' => 8, 'boris' => 7]);
        $this->rounds->addFilm(1, 'oldie', 'anna', 1, $monday);
    }

    private function command(RecordingTelegramClient $client, Rasterizer $rasterizer): PostFlashbackCommand
    {
        return new PostFlashbackCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            new MicroPoster(new PanelsCard(), $rasterizer, $client, '-100500'),
        );
    }

    private function tester(PostFlashbackCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-flashback'));
    }
}
