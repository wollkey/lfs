<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostHighlightCommand;
use App\Telegram\Card\PanelsCard;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\FakeRasterizer;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostHighlightCommand::class)]
final class PostHighlightCommandTest extends IntegrationTestCase
{
    public function testPostsAHighlightWhenTheCurrentRoundHasData(): void
    {
        $this->seedRound();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->photos);
    }

    public function testSkipsWhenThereIsNoRound(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertSame([], $client->sent);
    }

    private function seedRound(): void
    {
        $this->givenMembers('anna', 'boris');
        $this->givenRound(1, '2026-01-06');
        $this->givenFilmRatedBy('split', ['anna' => 2, 'boris' => 9]);
        $this->givenFilmRatedBy('agreed', ['anna' => 7, 'boris' => 7]);
        $this->rounds->addFilm(1, 'split', 'anna', 1, '2026-01-06');
        $this->rounds->addFilm(1, 'agreed', 'boris', 2, '2026-01-13');
    }

    private function command(RecordingTelegramClient $client): PostHighlightCommand
    {
        return new PostHighlightCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            new MicroPoster(new PanelsCard(), new FakeRasterizer(), $client, '-100500'),
        );
    }

    private function tester(PostHighlightCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-highlight'));
    }
}
