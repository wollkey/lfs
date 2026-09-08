<?php

declare(strict_types=1);

namespace App\Tests\Console;

use App\Console\SeedCommand;
use App\Domain\Film;
use App\Domain\MemberStatus;
use App\Letterboxd\FilmPage;
use App\Letterboxd\Parser\ActivityParser;
use App\Letterboxd\Parser\FilmPageParser;
use App\Letterboxd\Parser\FriendsRatingsParser;
use App\Letterboxd\Parser\ListParser;
use App\Letterboxd\Posters;
use App\Seeding\NewFilms;
use App\Seeding\ScrapedRatings;
use App\Tests\Common\IntegrationTestCase;
use App\Tests\Letterboxd\RecordingDownloader;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SeedCommand::class)]
#[CoversClass(NewFilms::class)]
#[CoversClass(ScrapedRatings::class)]
#[CoversClass(FilmPage::class)]
final class SeedCommandTest extends IntegrationTestCase
{
    private const string JPEG = "\xFF\xD8\xFF\xE0";

    private string $htmlDir;
    private string $posterDir;
    private RecordingDownloader $downloader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->htmlDir = sys_get_temp_dir().'/lfs-html-'.bin2hex(random_bytes(4));
        $this->posterDir = sys_get_temp_dir().'/lfs-posters-'.bin2hex(random_bytes(4));
        mkdir($this->htmlDir.'/friends', 0o775, true);
        mkdir($this->htmlDir.'/friends_activity', 0o775, true);

        $this->downloader = new RecordingDownloader([
            '/film/drive-2011/' => (string) file_get_contents(LFS_ROOT.'/tests/Letterboxd/Fixtures/film_page.html'),
            'a.ltrbxd.com' => self::JPEG.str_repeat('x', 2048),
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->htmlDir);
        $this->removeTree($this->posterDir);
    }

    public function testAddsTheScrapedFilmWithItsPickerSlotAndPoster(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRoundFilm(6, 'city-lights', 1, 'wollkey');
        $this->givenPoster('city-lights');
        $this->givenFriendsPage('drive-2011');

        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['html-dir' => $this->htmlDir]));

        self::assertSame('Drive', $this->films->find('drive-2011')?->title);
        self::assertSame([6, 2, 'lenka', date('Y-m-d')], $this->slot('drive-2011'));
        self::assertFileExists($this->posterDir.'/drive-2011.jpg');
    }

    public function testStartsANewRoundOnceEveryActiveMemberHasPicked(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRoundFilm(6, 'city-lights', 1, 'wollkey');
        $this->givenRoundFilm(6, 'stalker', 2, 'lenka');
        $this->givenPoster('city-lights');
        $this->givenPoster('stalker');
        $this->givenFriendsPage('drive-2011');

        $tester = $this->tester();
        $tester->execute(['html-dir' => $this->htmlDir]);

        self::assertSame([7, 1, 'wollkey', date('Y-m-d')], $this->slot('drive-2011'));
    }

    public function testPickerIsWhoeverHasNotPickedInThisRoundYet(): void
    {
        $this->givenMembers('lenka', 'christallisme', 'wollkey', 'psy667');
        $this->givenRoundFilm(6, 'city-lights', 1, 'lenka');
        $this->givenRoundFilm(6, 'stalker', 2, 'christallisme');
        $this->givenPoster('city-lights');
        $this->givenPoster('stalker');
        $this->givenFriendsPage('drive-2011');

        $tester = $this->tester();
        $tester->execute(['html-dir' => $this->htmlDir]);

        self::assertSame('wollkey', $this->slot('drive-2011')[2]);
        self::assertStringContainsString('picked by wollkey', $tester->getDisplay());
    }

    public function testPickerSkipsAMemberWhoAlreadyTookTheirTurnOutOfOrder(): void
    {
        $this->givenMembers('lenka', 'christallisme', 'wollkey', 'psy667');
        $this->givenRoundFilm(6, 'city-lights', 1, 'lenka');
        $this->givenRoundFilm(6, 'stalker', 2, 'wollkey');
        $this->givenPoster('city-lights');
        $this->givenPoster('stalker');
        $this->givenFriendsPage('drive-2011');

        $this->tester()->execute(['html-dir' => $this->htmlDir]);

        self::assertSame('christallisme', $this->slot('drive-2011')[2]);
    }

    public function testFormerMembersAreOutOfTheRotation(): void
    {
        $this->givenMember('justdanya', MemberStatus::Former, 1);
        $this->givenMembers('lenka', 'christallisme');
        $this->givenRoundFilm(6, 'city-lights', 1, 'lenka');
        $this->givenPoster('city-lights');
        $this->givenFriendsPage('drive-2011');

        $this->tester()->execute(['html-dir' => $this->htmlDir]);

        self::assertSame('christallisme', $this->slot('drive-2011')[2]);
    }

    public function testClearingAPositionTakesAMemberOutOfTheRotationAndTheRoundSize(): void
    {
        $this->givenMembers('lenka', 'christallisme');
        $this->givenMember('wollkey', position: null);
        $this->givenRoundFilm(6, 'city-lights', 1, 'lenka');
        $this->givenRoundFilm(6, 'stalker', 2, 'christallisme');
        $this->givenPoster('city-lights');
        $this->givenPoster('stalker');
        $this->givenFriendsPage('drive-2011');

        $this->tester()->execute(['html-dir' => $this->htmlDir]);

        // Both members in the rotation have picked, so the round is full at two.
        self::assertSame([7, 1, 'lenka', date('Y-m-d')], $this->slot('drive-2011'));
    }

    public function testImportsRatingsFromFriendsAndActivity(): void
    {
        $this->givenMembers('atomic_rage', 'vika');
        $this->givenKnownFilm('citizen-kane');
        $this->givenKnownFilm('drive-2011');
        $this->givenFixture('friends/citizen-kane.html', 'friends_film.html');
        $this->givenFixture('friends_activity/vika.html', 'activity_pagination.html');

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['html-dir' => $this->htmlDir]));

        self::assertSame(7, $this->ratings->findScore('citizen-kane', 'atomic_rage'));
        self::assertSame(6, $this->ratings->findScore('drive-2011', 'vika'));
    }

    public function testPicksUpAChangedRatingOnAnOldFilm(): void
    {
        $this->givenMembers('atomic_rage');
        $this->givenKnownFilm('citizen-kane');
        $this->ratings->setRating('citizen-kane', 'atomic_rage', 3);
        $this->givenFixture('friends/citizen-kane.html', 'friends_film.html');

        $tester = $this->tester();
        $tester->execute(['html-dir' => $this->htmlDir]);

        self::assertSame(7, $this->ratings->findScore('citizen-kane', 'atomic_rage'));
        self::assertStringContainsString('0 new, 1 changed', $tester->getDisplay());
    }

    public function testRerunChangesNothing(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'atomic_rage');
        $this->givenRoundFilm(6, 'city-lights', 1);
        $this->givenPoster('city-lights');
        $this->givenFriendsPage('drive-2011');
        $this->givenFixture('friends/citizen-kane.html', 'friends_film.html');
        $this->givenKnownFilm('citizen-kane');

        $first = $this->tester();
        $first->execute(['html-dir' => $this->htmlDir]);

        $before = $this->dump();

        $second = $this->tester();
        self::assertSame(Command::SUCCESS, $second->execute(['html-dir' => $this->htmlDir]));

        self::assertSame($before, $this->dump());
        self::assertStringContainsString('Ratings: nothing changed.', $second->getDisplay());
    }

    public function testRunsWithoutAListPage(): void
    {
        self::assertFileDoesNotExist($this->htmlDir.'/list.html');

        $this->givenMembers('wollkey', 'lenka');
        $this->givenRoundFilm(6, 'city-lights', 1);
        $this->givenPoster('city-lights');
        $this->givenFriendsPage('drive-2011');

        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['html-dir' => $this->htmlDir]));
        self::assertNotNull($this->films->find('drive-2011'));
    }

    public function testKeepsAnExistingPickerPickDateAndReview(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'atomic_rage');
        $this->givenRoundFilm(6, 'drive-2011', 2, 'wollkey', '2024-01-01');
        $this->givenPoster('drive-2011');
        $this->givenFriendsPage('drive-2011');
        $this->givenKnownFilm('citizen-kane');
        $this->givenFixture('friends/citizen-kane.html', 'friends_film.html');
        $this->pdo->exec("UPDATE ratings SET review = 'kept' WHERE 1");

        $this->tester()->execute(['html-dir' => $this->htmlDir]);

        self::assertSame([6, 2, 'wollkey', '2024-01-01'], $this->slot('drive-2011'));
    }

    public function testSkipsRatersWhoAreNotClubMembers(): void
    {
        $this->givenMembers('atomic_rage');
        $this->givenKnownFilm('citizen-kane');
        $this->givenFixture('friends/citizen-kane.html', 'friends_film.html');

        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['html-dir' => $this->htmlDir]));

        self::assertSame(7, $this->ratings->findScore('citizen-kane', 'atomic_rage'));
        self::assertCount(1, $this->ratings->scores());
        self::assertStringContainsString('not a club member', $tester->getDisplay());
    }

    private function givenKnownFilm(string $slug): void
    {
        $this->films->save(new Film($slug, ucfirst($slug)));
        $this->givenPoster($slug);
    }

    private function givenRoundFilm(
        int $round,
        string $slug,
        int $position,
        ?string $pickedBy = null,
        string $pickedOn = '2026-08-23',
    ): void {
        $this->films->save(new Film($slug, ucfirst($slug)));
        $this->givenRound($round);
        $this->rounds->addFilm($round, $slug, $pickedBy, $position, $pickedOn);
    }

    private function givenFriendsPage(string $slug): void
    {
        file_put_contents("{$this->htmlDir}/friends/{$slug}.html", '<html></html>');
    }

    private function givenFixture(string $target, string $fixture): void
    {
        copy(LFS_ROOT."/tests/Letterboxd/Fixtures/{$fixture}", "{$this->htmlDir}/{$target}");
    }

    private function givenPoster(string $slug): void
    {
        if (!is_dir($this->posterDir)) {
            mkdir($this->posterDir, 0o775, true);
        }
        file_put_contents("{$this->posterDir}/{$slug}.jpg", self::JPEG);
    }

    /**
     * @return array{int, int, ?string, string}
     */
    private function slot(string $slug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT round_number, position, picked_by, picked_on FROM round_films WHERE film_slug = :slug',
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return [(int) $row['round_number'], (int) $row['position'], $row['picked_by'], $row['picked_on']];
    }

    private function dump(): string
    {
        $tables = ['films', 'round_films', 'ratings'];
        $rows = [];
        foreach ($tables as $table) {
            $rows[$table] = $this->pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        }

        return (string) json_encode($rows);
    }

    private function removeTree(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $path) {
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    private function tester(): CommandTester
    {
        $filmPage = new FilmPage($this->downloader, new FilmPageParser());
        $posters = new Posters($this->downloader, $this->posterDir);

        $application = new Application();
        $application->addCommand(new SeedCommand(
            new ListParser(),
            new NewFilms($filmPage, $this->films, $this->members, $this->rounds),
            new ScrapedRatings(
                new ListParser(),
                new FriendsRatingsParser(),
                new ActivityParser(),
                $this->films,
                $this->members,
                $this->ratings,
            ),
            $filmPage,
            $posters,
            $this->films,
        ));

        return new CommandTester($application->find('seed'));
    }
}
