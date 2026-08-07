<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Domain\MemberStatus;
use App\Telegram\Cell;
use App\Telegram\Messages;
use App\Telegram\Post;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Messages::class)]
#[CoversClass(Post::class)]
final class MessagesTest extends IntegrationTestCase
{
    public function testForCommandMapsKnownCommandsAndIgnoresTheRest(): void
    {
        $this->givenMembers('wollkey');
        $messages = $this->messages();

        self::assertInstanceOf(Post::class, $messages->forCommand('/members'));
        self::assertInstanceOf(Post::class, $messages->forCommand('/films'));
        self::assertInstanceOf(Post::class, $messages->forCommand('/links'));
        self::assertNull($messages->forCommand('/unknown'));
        self::assertNull($messages->forCommand('hello there'));
        self::assertNull($messages->forCommand(''));
    }

    public function testForCommandStripsBotSuffixIgnoresCaseAndArguments(): void
    {
        $this->givenMembers('wollkey');
        $messages = $this->messages();

        self::assertEquals($messages->activeMembers(), $messages->forCommand('/members@LfsBot'));
        self::assertEquals($messages->activeMembers(), $messages->forCommand('/MEMBERS'));
        self::assertEquals($messages->activeMembers(), $messages->forCommand('  /members  extra args '));
    }

    public function testActiveMembersListsOnlyActiveMembers(): void
    {
        $this->givenMember('wollkey');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 8, 'justdanya' => 7]);

        $names = $this->column($this->messages()->activeMembers()->table->rows, 1);

        self::assertContains('Wollkey', $names);
        self::assertNotContains('Justdanya', $names);
    }

    public function testActiveMembersLinkNamesToLetterboxd(): void
    {
        $this->givenMember('wollkey');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 8]);

        $rows = $this->messages()->activeMembers()->table->rows;

        self::assertSame('https://letterboxd.com/wollkey/', $rows[0][1]->url);
    }

    public function testWatchedFilmsReturnsALinkToTheSite(): void
    {
        $post = $this->messages()->watchedFilms();

        self::assertNull($post->table);
        self::assertCount(1, $post->links);
        self::assertSame('https://lfs.wollkey.ru/films', $post->links[0]->url);
    }

    public function testLinksListsTheSitePages(): void
    {
        $urls = array_map(static fn ($cell): ?string => $cell->url, $this->messages()->links()->links);

        self::assertSame([
            'https://lfs.wollkey.ru/',
            'https://lfs.wollkey.ru/films',
            'https://lfs.wollkey.ru/rounds',
            'https://lfs.wollkey.ru/members',
        ], $urls);
    }

    public function testCurrentRoundStandingsCoversTheLatestRoundSortedByAverage(): void
    {
        $this->givenMembers('wollkey', 'lenka');
        $this->givenRound(1);
        $this->givenRound(2);
        $this->givenFilmRatedBy('low', ['wollkey' => 4, 'lenka' => 6]);
        $this->givenFilmRatedBy('high', ['wollkey' => 9, 'lenka' => 9]);
        $this->rounds->addFilm(2, 'low', 'wollkey', 1, '2025-06-30');
        $this->rounds->addFilm(2, 'high', 'lenka', 2, '2025-07-07');

        $post = $this->messages()->currentRoundStandings();

        self::assertStringContainsString('Круг 2', $post->title);
        self::assertSame(['High', 'Low'], $this->column($post->table->rows, 1));
    }

    public function testCurrentRoundStandingsWithoutRounds(): void
    {
        $post = $this->messages()->currentRoundStandings();

        self::assertNull($post->table);
        self::assertSame('Круги ещё не начались.', $post->intro);
    }

    public function testRoundSummaryBuildsAnAlbumOfCardsWithACaption(): void
    {
        $this->seedSummaryRound();

        ['caption' => $caption, 'cards' => $cards] = $this->messages()->roundSummary(1);

        self::assertCount(4, $cards);
        self::assertStringContainsString('Круг 1', $caption);
        self::assertNotSame([], $cards[0]->table->headers);
        self::assertSame([], $cards[1]->table->headers);
        self::assertSame([], $cards[2]->table->headers);
        self::assertNotSame([], $cards[3]->table->headers);
        self::assertSame(['Unity', 'Flop'], $this->column($cards[0]->table->rows, 1));
    }

    public function testRoundSummaryCaptionLeadsWithTheBestFilm(): void
    {
        $this->seedSummaryRound();

        $caption = $this->messages()->roundSummary(1)['caption'];

        self::assertStringContainsString('Лучший фильм: Unity', $caption);
    }

    public function testRoundSummaryRendersTiesAsCommaSeparatedNames(): void
    {
        $this->seedSummaryRound();

        $memberAwards = $this->messages()->roundSummary(1)['cards'][2];

        self::assertSame('Anna, Boris', $memberAwards->table->rows[0][1]->text);
    }

    public function testRoundSummaryFallsBackWhenTheRoundIsEmpty(): void
    {
        ['caption' => $caption, 'cards' => $cards] = $this->messages()->roundSummary(9);

        self::assertSame([], $cards);
        self::assertStringContainsString('ещё не собрал фильмов', $caption);
    }

    public function testFlashbackSurfacesFilmsPickedAYearAgoThisWeek(): void
    {
        $this->givenMembers('anna', 'boris');
        $this->givenRound(1);
        $this->givenFilmRatedBy('oldie', ['anna' => 8, 'boris' => 7]);
        $this->rounds->addFilm(1, 'oldie', 'anna', 1, '2025-08-04');

        $post = $this->messages()->flashback(new \DateTimeImmutable('2026-08-06'));

        self::assertNotNull($post);
        self::assertSame(['/posters/oldie.jpg'], $post->images);
        self::assertStringContainsString('Год назад в этот день', (string) $post->intro);
        self::assertStringContainsString('<b><a href="https://lfs.wollkey.ru/films/oldie">Oldie</a></b> - 7.5 (2 оценок)', (string) $post->intro);
    }

    public function testFlashbackReturnsNullWhenNothingWatchedThatWeek(): void
    {
        $this->givenMembers('anna');
        $this->givenRound(1);
        $this->givenFilmRatedBy('oldie', ['anna' => 8]);
        $this->rounds->addFilm(1, 'oldie', 'anna', 1, '2025-08-04');

        self::assertNull($this->messages()->flashback(new \DateTimeImmutable('2026-12-01')));
    }

    public function testWeeklyHighlightSpotlightsTheLatestRatedFilm(): void
    {
        $this->givenMembers('anna', 'boris', 'clara');
        $this->givenRound(1);
        $this->givenFilmRatedBy('older', ['anna' => 5, 'boris' => 6]);
        $this->givenFilmRatedBy('newer', ['anna' => 3, 'boris' => 9, 'clara' => 6]);
        $this->rounds->addFilm(1, 'older', 'anna', 1, '2026-01-06');
        $this->rounds->addFilm(1, 'newer', 'boris', 2, '2026-01-13');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertSame(['/posters/newer.jpg'], $post->images);
        self::assertStringContainsString('Последний кадр', (string) $post->intro);
        self::assertStringContainsString('<b><a href="https://lfs.wollkey.ru/films/newer">Newer</a></b>', (string) $post->intro);
        self::assertStringContainsString('Высшая оценка: Boris (9)', (string) $post->intro);
        self::assertStringContainsString('Низшая оценка: Anna (3)', (string) $post->intro);
    }

    public function testWeeklyHighlightListsAllTiedTopAndBottomVoters(): void
    {
        $this->givenMembers('anna', 'boris', 'clara', 'dima');
        $this->givenRound(1);
        $this->givenFilmRatedBy('film', ['anna' => 9, 'boris' => 9, 'clara' => 4, 'dima' => 4]);
        $this->rounds->addFilm(1, 'film', 'anna', 1, '2026-03-02');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertStringContainsString('Высшая оценка: Anna, Boris (9)', (string) $post->intro);
        self::assertStringContainsString('Низшая оценка: Clara, Dima (4)', (string) $post->intro);
    }

    public function testWeeklyHighlightCollapsesToUnanimousWhenEveryoneAgrees(): void
    {
        $this->givenMembers('anna', 'boris');
        $this->givenRound(1);
        $this->givenFilmRatedBy('film', ['anna' => 8, 'boris' => 8]);
        $this->rounds->addFilm(1, 'film', 'anna', 1, '2026-03-02');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertStringContainsString('Единогласно - 8', (string) $post->intro);
    }

    public function testWeeklyHighlightReturnsNullWithoutAQualifiedFilm(): void
    {
        self::assertNull($this->messages()->weeklyHighlight());
    }

    private function seedSummaryRound(): void
    {
        $this->givenMembers('anna', 'boris', 'clara');
        $this->givenRound(1, '2025-01-06', '2025-03-10');
        $this->givenFilmRatedBy('unity', ['anna' => 8, 'boris' => 8, 'clara' => 8]);
        $this->givenFilmRatedBy('flop', ['anna' => 2, 'boris' => 2]);
        $this->rounds->addFilm(1, 'unity', 'anna', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'flop', 'boris', 2, '2025-01-13');
    }

    private function messages(): Messages
    {
        return new Messages($this->statistics(quorum: 1), 'https://lfs.wollkey.ru', '/posters');
    }

    /**
     * @param list<list<Cell>> $rows
     *
     * @return list<string>
     */
    private function column(array $rows, int $index): array
    {
        return array_map(static fn (array $row): string => $row[$index]->text, $rows);
    }
}
