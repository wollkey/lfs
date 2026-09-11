<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\ImportReviewsCommand;
use App\Domain\Film;
use App\Domain\ReviewSource;
use App\Reviewing\Watermark;
use App\Telegram\Inbox\CapturedMessage;
use App\Telegram\Inbox\LogReader;
use App\Telegram\Inbox\MessageKind;
use App\Telegram\Inbox\MessageLog;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ImportReviewsCommand::class)]
final class ImportReviewsCommandTest extends IntegrationTestCase
{
    private const int STALKER_ANNOUNCEMENT = 500;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/lfs-import-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testAPinnedAnnouncementLetsTheNextReplyLandOnItsOwn(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->announcement());
        $this->given($this->message(4242, 'Повторный просмотр дался легче.', replyTo: self::STALKER_ANNOUNCEMENT));

        $console = $this->console();

        self::assertSame(Command::SUCCESS, $console->execute([]));

        $review = $this->reviews->find('stalker', 'christallisme');
        self::assertNotNull($review);
        self::assertSame('Повторный просмотр дался легче.', $review->body);
        self::assertSame(ReviewSource::Telegram, $review->source);
        self::assertSame(321, $review->telegramMessageId);
    }

    public function testALetterboxdLinkIsEnoughOnItsOwn(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->message(
            4242,
            'Отличное кино https://letterboxd.com/film/stalker/',
            urls: ['https://letterboxd.com/film/stalker/'],
        ));

        self::assertSame(Command::SUCCESS, $this->console()->execute([]));
        self::assertNotNull($this->reviews->find('stalker', 'christallisme'));
    }

    public function testAnUnknownAuthorIsMappedOnceAndRemembered(): void
    {
        $this->givenClub();

        $this->given($this->announcement());
        $this->given($this->message(4242, 'Мне не зашло совсем.', replyTo: self::STALKER_ANNOUNCEMENT));

        $console = $this->console();
        $console->setInputs(['Christallisme (@christallisme)']);

        self::assertSame(Command::SUCCESS, $console->execute([]));

        self::assertNotNull($this->reviews->find('stalker', 'christallisme'));
        self::assertSame([4242 => 'christallisme'], $this->members->byTelegramId());
    }

    public function testTheSameAuthorIsAskedAboutOnlyOncePerRun(): void
    {
        $this->givenClub();
        $this->films->save(new Film('alien', 'Alien'));
        $this->rounds->addFilm(6, 'alien', 'al1vka', 2, '2026-09-08');

        $this->given($this->announcement());
        $this->given($this->announcement('alien', 501));
        $this->given($this->message(4242, 'Первый отзыв.', replyTo: self::STALKER_ANNOUNCEMENT, messageId: 321));
        $this->given($this->message(4242, 'Второй отзыв.', replyTo: 501, messageId: 322));

        $console = $this->console();
        $console->setInputs(['Christallisme (@christallisme)']);

        self::assertSame(Command::SUCCESS, $console->execute([]));

        self::assertNotNull($this->reviews->find('stalker', 'christallisme'));
        self::assertNotNull($this->reviews->find('alien', 'christallisme'));
    }

    public function testAnUnmatchedMessageIsOfferedForAManualChoice(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->message(4242, 'Досмотрел вчера, впечатления смешанные.'));

        $console = $this->console();
        $console->setInputs(['Stalker']);

        self::assertSame(Command::SUCCESS, $console->execute([]));
        self::assertNotNull($this->reviews->find('stalker', 'christallisme'));
    }

    public function testEnterMeansNotAReviewAndIsNeverAskedAgain(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->message(4242, 'Кто-нибудь помнит, во сколько мы договорились?'));

        $console = $this->console();
        $console->setInputs(['']);

        self::assertSame(Command::SUCCESS, $console->execute([]));
        self::assertSame([], $this->reviews->all());

        $second = $this->console();
        self::assertSame(Command::SUCCESS, $second->execute([]));
        self::assertStringNotContainsString('Какой фильм?', $second->getDisplay());
    }

    public function testAlreadyImportedMessagesAreLeftAlone(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->announcement());
        $this->given($this->message(4242, 'Первый заход.', replyTo: self::STALKER_ANNOUNCEMENT));

        self::assertSame(Command::SUCCESS, $this->console()->execute([]));

        $second = $this->console();
        self::assertSame(Command::SUCCESS, $second->execute([]));
        self::assertStringContainsString('отзывов 0', $second->getDisplay());
    }

    public function testAnEditedMessageOverwritesTheStoredReview(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->announcement());
        $this->given($this->message(4242, 'Первый заход.', replyTo: self::STALKER_ANNOUNCEMENT));
        self::assertSame(Command::SUCCESS, $this->console()->execute([]));

        $this->given($this->message(4242, 'Первый заход. Дополню: финал вытягивает.', replyTo: self::STALKER_ANNOUNCEMENT, kind: MessageKind::Edited, updateId: 999_999));
        self::assertSame(Command::SUCCESS, $this->console()->execute([]));

        self::assertSame('Первый заход. Дополню: финал вытягивает.', $this->reviews->find('stalker', 'christallisme')?->body);
    }

    public function testADryRunTouchesNothing(): void
    {
        $this->givenClub();
        $this->givenLink('christallisme', 4242);

        $this->given($this->announcement());
        $this->given($this->message(4242, 'Отзыв, который не должен сохраниться.', replyTo: self::STALKER_ANNOUNCEMENT));

        $console = $this->console();

        self::assertSame(Command::SUCCESS, $console->execute(['--dry-run' => true]));
        self::assertSame([], $this->reviews->all());
        self::assertStringContainsString('Пробный прогон', $console->getDisplay());
    }

    private function givenClub(): void
    {
        $this->givenMembers('christallisme', 'al1vka');
        $this->givenRound(6);
        $this->films->save(new Film('stalker', 'Stalker'));
        $this->rounds->addFilm(6, 'stalker', 'christallisme', 1, '2026-09-05');
    }

    private function givenLink(string $username, int $telegramUserId): void
    {
        $this->members->linkTelegram($username, $telegramUserId);
    }

    private function given(CapturedMessage $message): void
    {
        new MessageLog($this->dir)->append($message);
    }

    private function announcement(string $slug = 'stalker', int $messageId = self::STALKER_ANNOUNCEMENT): CapturedMessage
    {
        return new CapturedMessage(
            MessageKind::Pin,
            $messageId,
            -100,
            $messageId,
            1_789_038_000,
            1,
            'wollkey',
            'На этой неделе смотрим '.$slug,
            ['https://letterboxd.com/film/'.$slug.'/'],
        );
    }

    /**
     * @param list<string> $urls
     */
    private function message(
        int $authorId,
        string $text,
        ?int $replyTo = null,
        array $urls = [],
        MessageKind $kind = MessageKind::Message,
        int $messageId = 321,
        ?int $updateId = null,
    ): CapturedMessage {
        return new CapturedMessage(
            $kind,
            $updateId ?? $authorId + $messageId + ($replyTo ?? 0),
            -100,
            $messageId,
            1_789_041_600,
            $authorId,
            'christallisme',
            $text,
            $urls,
            [],
            $replyTo,
        );
    }

    private function console(): CommandTester
    {
        $command = new ImportReviewsCommand(
            new LogReader($this->dir),
            $this->members,
            $this->films,
            $this->rounds,
            $this->reviews,
            new Watermark($this->dir.'/imported-through.txt'),
        );

        new Application()->addCommand($command);

        return new CommandTester($command);
    }
}
