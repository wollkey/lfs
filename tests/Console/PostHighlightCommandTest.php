<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostHighlightCommand;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostHighlightCommand::class)]
final class PostHighlightCommandTest extends IntegrationTestCase
{
    private string $postersDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postersDir = sys_get_temp_dir().'/lfs_posters_'.uniqid();
        mkdir($this->postersDir, 0o775, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->postersDir.'/*') ?: []);
        @rmdir($this->postersDir);
    }

    public function testPostsThePosterOfTheLatestRatedFilm(): void
    {
        $this->seedRound();
        $this->givenPoster('latest');
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->photos);
        self::assertStringEndsWith('/latest.jpg', $client->photos[0]['imagePath']);
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
        $this->givenRound(1);
        $this->givenFilmRatedBy('older', ['anna' => 5, 'boris' => 6]);
        $this->givenFilmRatedBy('latest', ['anna' => 4, 'boris' => 9]);
        $this->rounds->addFilm(1, 'older', 'anna', 1, '2026-01-06');
        $this->rounds->addFilm(1, 'latest', 'boris', 2, '2026-01-13');
    }

    private function givenPoster(string $slug): void
    {
        file_put_contents($this->postersDir.'/'.$slug.'.jpg', 'JPG');
    }

    private function command(RecordingTelegramClient $client): PostHighlightCommand
    {
        return new PostHighlightCommand(
            new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru', $this->postersDir),
            new MicroPoster($client, '-100500'),
        );
    }

    private function tester(PostHighlightCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-highlight'));
    }
}
