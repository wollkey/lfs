<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\PostFlashbackCommand;
use App\Telegram\Messages;
use App\Telegram\MicroPoster;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PostFlashbackCommand::class)]
#[CoversClass(MicroPoster::class)]
final class PostFlashbackCommandTest extends IntegrationTestCase
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

    public function testPostsThePosterWithACaption(): void
    {
        $this->seedFlashback();
        $this->givenPoster('oldie');
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertCount(1, $client->photos);
        self::assertStringContainsString('Год назад', $client->photos[0]['caption']);
        self::assertStringEndsWith('/oldie.jpg', $client->photos[0]['imagePath']);
    }

    public function testFallsBackToTextWhenThePosterIsMissing(): void
    {
        $this->seedFlashback();
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertCount(1, $client->sent);
    }

    public function testSkipsWhenNothingWatchedThatWeekAYearAgo(): void
    {
        $client = new RecordingTelegramClient();

        $exit = $this->tester($this->command($client))->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame([], $client->photos);
        self::assertSame([], $client->sent);
    }

    public function testReturnsFailureWhenTelegramErrors(): void
    {
        $this->seedFlashback();
        $this->givenPoster('oldie');
        $client = new RecordingTelegramClient(fail: true);

        self::assertSame(Command::FAILURE, $this->tester($this->command($client))->execute([]));
    }

    public function testReturnsInvalidWhenUnconfigured(): void
    {
        $this->seedFlashback();
        $this->givenPoster('oldie');
        $command = new PostFlashbackCommand($this->messages(), new MicroPoster(null, null));

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

    private function givenPoster(string $slug): void
    {
        file_put_contents($this->postersDir.'/'.$slug.'.jpg', 'JPG');
    }

    private function messages(): Messages
    {
        return new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru', $this->postersDir);
    }

    private function command(RecordingTelegramClient $client): PostFlashbackCommand
    {
        return new PostFlashbackCommand($this->messages(), new MicroPoster($client, '-100500'));
    }

    private function tester(PostFlashbackCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('bot:post-flashback'));
    }
}
