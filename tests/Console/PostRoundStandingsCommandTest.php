<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostRoundStandingsCommand;
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

#[CoversClass(PostRoundStandingsCommand::class)]
final class PostRoundStandingsCommandTest extends IntegrationTestCase
{
    public function testPostsTheImageCardToTheConfiguredChat(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->sent);
        self::assertCount(1, $client->photos);
        self::assertSame('-100500', $client->photos[0]['chatId']);
        self::assertStringContainsString('Круг', $client->photos[0]['caption']);
    }

    public function testFallsBackToTheTableWhenRenderingFails(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer(fail: true)))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertCount(1, $client->sent);
        self::assertNotNull($client->sent[0]['post']->table);
    }

    public function testReturnsInvalidWhenUnconfigured(): void
    {
        $command = new PostRoundStandingsCommand(
            new Messages($this->statistics()),
            new StandingsCard(),
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
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client, new FakeRasterizer()))->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->sent);
    }

    private function seedRound(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1);
    }

    private function command(RecordingTelegramClient $client, Rasterizer $rasterizer): PostRoundStandingsCommand
    {
        return new PostRoundStandingsCommand(
            new Messages($this->statistics(quorum: 1)),
            new StandingsCard(),
            $rasterizer,
            $client,
            '-100500',
        );
    }

    private function tester(PostRoundStandingsCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-round'));
    }
}
