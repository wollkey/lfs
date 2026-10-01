<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostSpotlightCommand;
use App\Telegram\Card\SpotlightCard;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\FakeRasterizer;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostSpotlightCommand::class)]
final class PostSpotlightCommandTest extends IntegrationTestCase
{
    public function testPostsTheNameCardWithTheCaption(): void
    {
        $this->seedPickThisWeek();
        $client = new RecordingTelegramClient();

        $exit = $this->console($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->photos);
        self::assertStringContainsString('Неделя славы - Al1vka', $client->photos[0]['caption']);
        self::assertFileDoesNotExist($client->photos[0]['imagePath']);
    }

    public function testFallsBackToTextWhenTheCardCannotBeRendered(): void
    {
        $this->seedPickThisWeek();
        $client = new RecordingTelegramClient();

        $exit = $this->console($this->command($client, new FakeRasterizer(fail: true)))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertCount(1, $client->texts);
    }

    public function testSkipsWhenNobodyPickedThisWeek(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->console($this->command($client, new FakeRasterizer()))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertSame([], $client->texts);
    }

    private function seedPickThisWeek(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('interstellar', ['christallisme' => 9]);
        $this->rounds->addFilm(1, 'interstellar', 'al1vka', 1, new \DateTimeImmutable('monday this week')->format('Y-m-d'));
    }

    private function command(RecordingTelegramClient $client, FakeRasterizer $rasterizer): PostSpotlightCommand
    {
        return new PostSpotlightCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru'),
            new SpotlightCard(),
            $rasterizer,
            new MicroPoster($client, '-100500'),
        );
    }

    private function console(PostSpotlightCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-spotlight'));
    }
}
