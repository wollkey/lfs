<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostRoundStandingsCommand;
use App\Telegram\Messages;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostRoundStandingsCommand::class)]
final class PostRoundStandingsCommandTest extends IntegrationTestCase
{
    public function testDryRunPrintsWithoutSending(): void
    {
        $client = new RecordingTelegramClient();
        $tester = $this->tester(new PostRoundStandingsCommand(new Messages($this->statistics()), $client, '123'));

        $exit = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->sent);
    }

    public function testPostsStandingsToTheConfiguredChat(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1);

        $client = new RecordingTelegramClient();
        $command = new PostRoundStandingsCommand(new Messages($this->statistics(quorum: 1)), $client, '-100500');

        $exit = $this->tester($command)->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->sent);
        self::assertSame('-100500', $client->sent[0]['chatId']);
        self::assertContains('Stalker', array_column($client->sent[0]['post']->table->rows, 1));
    }

    public function testReturnsInvalidWhenUnconfigured(): void
    {
        $command = new PostRoundStandingsCommand(new Messages($this->statistics()), null, null);

        self::assertSame(Command::INVALID, $this->tester($command)->execute([]));
    }

    public function testReturnsFailureWhenTelegramErrors(): void
    {
        $client = new RecordingTelegramClient(fail: true);
        $command = new PostRoundStandingsCommand(new Messages($this->statistics()), $client, '123');

        self::assertSame(Command::FAILURE, $this->tester($command)->execute([]));
    }

    private function tester(PostRoundStandingsCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-round'));
    }
}
