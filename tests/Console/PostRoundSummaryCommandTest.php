<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostRoundSummaryCommand;
use App\Telegram\Card\PanelsCard;
use App\Telegram\Card\StandingsCard;
use App\Telegram\Messages;
use App\Telegram\Rasterizer;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\FakeRasterizer;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostRoundSummaryCommand::class)]
final class PostRoundSummaryCommandTest extends IntegrationTestCase
{
    public function testPostsOneAlbumForTheCurrentRound(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->sent);
        self::assertCount(1, $client->albums);
        self::assertCount(4, $client->albums[0]['imagePaths']);
        self::assertSame('-100500', $client->albums[0]['chatId']);
        self::assertStringContainsString('Круг 1', $client->albums[0]['caption']);
    }

    public function testAcceptsAnExplicitRoundArgument(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute(['round' => 1]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->albums);
    }

    public function testReturnsInvalidWhenThereAreNoRounds(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::INVALID, $exit);
        self::assertSame([], $client->albums);
    }

    public function testReturnsInvalidWhenUnconfigured(): void
    {
        $this->seedRound();

        $command = new PostRoundSummaryCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            $this->statistics(quorum: 1),
            new StandingsCard(),
            new PanelsCard(),
            new FakeRasterizer(),
            null,
            null,
        );

        self::assertSame(Command::INVALID, $this->tester($command)->execute([]));
    }

    public function testReturnsFailureWhenTelegramErrors(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient(fail: true);

        self::assertSame(Command::FAILURE, $this->tester($this->command($client, new FakeRasterizer()))->execute([]));
    }

    public function testDryRunDoesNotSend(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->sent);
        self::assertSame([], $client->albums);
    }

    private function seedRound(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1, '2025-01-06', '2025-03-10');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8]);
        $this->givenFilmRatedBy('mother', ['wollkey' => 3, 'lenka' => 5]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'mother', 'lenka', 2, '2025-01-13');
    }

    private function command(RecordingTelegramClient $client, Rasterizer $rasterizer): PostRoundSummaryCommand
    {
        return new PostRoundSummaryCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            $this->statistics(quorum: 1),
            new StandingsCard(),
            new PanelsCard(),
            $rasterizer,
            $client,
            '-100500',
        );
    }

    private function tester(PostRoundSummaryCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-summary'));
    }
}
