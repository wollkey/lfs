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
        $this->givenMembers('wollkey', 'lenka_penka');
        $this->givenRound(1);
        $this->givenRound(2);
        $this->givenFilmRatedBy('low', ['wollkey' => 4, 'lenka_penka' => 6]);
        $this->givenFilmRatedBy('high', ['wollkey' => 9, 'lenka_penka' => 9]);
        $this->rounds->addFilm(2, 'low', 'wollkey', 1, '2025-06-30');
        $this->rounds->addFilm(2, 'high', 'lenka_penka', 2, '2025-07-07');

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

        self::assertSame('Al1vka, Christallisme', $memberAwards->table->rows[0][1]->text);
    }

    public function testRoundSummaryFallsBackWhenTheRoundIsEmpty(): void
    {
        ['caption' => $caption, 'cards' => $cards] = $this->messages()->roundSummary(9);

        self::assertSame([], $cards);
        self::assertStringContainsString('ещё не собрал фильмов', $caption);
    }

    public function testFlashbackSurfacesFilmsPickedAYearAgoThisWeek(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('oldie', ['al1vka' => 8, 'christallisme' => 7]);
        $this->rounds->addFilm(1, 'oldie', 'al1vka', 1, '2025-08-04');

        $post = $this->messages()->flashback(new \DateTimeImmutable('2026-08-06'));

        self::assertNotNull($post);
        self::assertSame(['/posters/oldie.jpg'], $post->images);
        self::assertStringContainsString('Год назад в этот день', (string) $post->intro);
        self::assertStringContainsString('<b><a href="https://lfs.wollkey.ru/films/oldie">Oldie</a></b> - 7.5 (2 оценок)', (string) $post->intro);
    }

    public function testFlashbackReturnsNullWhenNothingWatchedThatWeek(): void
    {
        $this->givenMembers('al1vka');
        $this->givenRound(1);
        $this->givenFilmRatedBy('oldie', ['al1vka' => 8]);
        $this->rounds->addFilm(1, 'oldie', 'al1vka', 1, '2025-08-04');

        self::assertNull($this->messages()->flashback(new \DateTimeImmutable('2026-12-01')));
    }

    public function testWeeklyHighlightSpotlightsTheLatestRatedFilm(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'koshmarus');
        $this->givenRound(1);
        $this->givenFilmRatedBy('older', ['al1vka' => 5, 'christallisme' => 6]);
        $this->givenFilmRatedBy('newer', ['al1vka' => 3, 'christallisme' => 9, 'koshmarus' => 6]);
        $this->rounds->addFilm(1, 'older', 'al1vka', 1, '2026-01-06');
        $this->rounds->addFilm(1, 'newer', 'christallisme', 2, '2026-01-13');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertSame(['/posters/newer.jpg'], $post->images);
        self::assertStringContainsString('Последний кадр', (string) $post->intro);
        self::assertStringContainsString('<b><a href="https://lfs.wollkey.ru/films/newer">Newer</a></b>', (string) $post->intro);
        self::assertStringContainsString('Высшая оценка: Christallisme (9)', (string) $post->intro);
        self::assertStringContainsString('Низшая оценка: Al1vka (3)', (string) $post->intro);
    }

    public function testWeeklyHighlightListsAllTiedTopAndBottomVoters(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'koshmarus', 'nickbiryukov');
        $this->givenRound(1);
        $this->givenFilmRatedBy('film', ['al1vka' => 9, 'christallisme' => 9, 'koshmarus' => 4, 'nickbiryukov' => 4]);
        $this->rounds->addFilm(1, 'film', 'al1vka', 1, '2026-03-02');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertStringContainsString('Высшая оценка: Al1vka, Christallisme (9)', (string) $post->intro);
        self::assertStringContainsString('Низшая оценка: Koshmarus, Nickbiryukov (4)', (string) $post->intro);
    }

    public function testWeeklyHighlightCollapsesToUnanimousWhenEveryoneAgrees(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('film', ['al1vka' => 8, 'christallisme' => 8]);
        $this->rounds->addFilm(1, 'film', 'al1vka', 1, '2026-03-02');

        $post = $this->messages()->weeklyHighlight();

        self::assertNotNull($post);
        self::assertStringContainsString('Единогласно - 8', (string) $post->intro);
    }

    public function testWeeklyHighlightReturnsNullWithoutAQualifiedFilm(): void
    {
        self::assertNull($this->messages()->weeklyHighlight());
    }

    public function testSpotlightTagsThePickerAndListsTheFacts(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->members->linkTelegram('al1vka', 42);
        $this->givenRound(1);
        $this->givenFilmRatedBy('arrival', ['al1vka' => 10, 'christallisme' => 6]);
        $this->givenFilmRatedBy('tenet', ['al1vka' => 5, 'christallisme' => 4]);
        $this->givenFilmRatedBy('interstellar', []);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2026-09-07');
        $this->rounds->addFilm(1, 'tenet', 'al1vka', 2, '2026-09-14');
        $this->rounds->addFilm(1, 'interstellar', 'al1vka', 3, '2026-09-28');

        $post = $this->messages()->spotlight(new \DateTimeImmutable('2026-09-29'));

        self::assertNotNull($post);
        self::assertEquals([[new Cell('Al1vka'), new Cell('Interstellar')]], $post->table?->rows);
        self::assertSame(implode("\n", [
            '<b>⭐ Неделя славы - <a href="tg://user?id=42">Al1vka</a></b>',
            'Выбор недели - <b><a href="https://lfs.wollkey.ru/films/interstellar">Interstellar</a></b>',
            '',
            '<b>🎬 Выбор фильмов</b>',
            '<blockquote>Лучший - <b><a href="https://lfs.wollkey.ru/films/arrival">Arrival</a></b> (8.0)',
            'Худший - <b><a href="https://lfs.wollkey.ru/films/tenet">Tenet</a></b> (4.5)',
            'Средний рейтинг - 6.3 (по клубу 6.3)</blockquote>',
            '',
            '<b>🍿 Оценки</b>',
            '<blockquote>Всего - 2, средняя 7.5 - на 2.5 выше, чем у клуба',
            '🌶️ Самая спорная - <b><a href="https://lfs.wollkey.ru/films/arrival">Arrival</a></b> (10 при средней 8.0)</blockquote>',
            '',
            '<b>❤️ Любимый фильм - 10 из 10</b>',
            '<blockquote expandable><b><a href="https://lfs.wollkey.ru/films/arrival">Arrival</a></b></blockquote>',
        ]), $post->intro);
    }

    public function testSpotlightOfADebutantWithoutATelegramAccount(): void
    {
        $this->givenMembers('christallisme', 'wollkey');
        $this->givenRound(1);
        $this->givenFilmRatedBy('stalker', ['christallisme' => 7, 'wollkey' => 7]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1, '2026-09-21');
        $this->givenFilmRatedBy('solaris', []);
        $this->rounds->addFilm(1, 'solaris', 'christallisme', 2, '2026-09-28');

        $intro = (string) $this->messages()->spotlight(new \DateTimeImmutable('2026-10-04'))?->intro;

        self::assertStringContainsString('Неделя славы - Christallisme</b>', $intro);
        self::assertStringContainsString('Первый выбор в клубе - дебют!', $intro);
        self::assertStringContainsString('Всего - 1, средняя 7.0', $intro);
        self::assertStringNotContainsString('чем у клуба', $intro);
        self::assertStringNotContainsString('Самая спорная', $intro);
    }

    public function testSpotlightListsEveryFavoriteInAnExpandableQuote(): void
    {
        $this->givenMembers('al1vka');
        $this->givenRound(1);
        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $slug) {
            $this->givenFilmRatedBy($slug, ['al1vka' => 10]);
            $this->rounds->addFilm(1, $slug, 'al1vka', $i + 1, '2026-09-0'.($i + 1));
        }
        $this->givenFilmRatedBy('latest', []);
        $this->rounds->addFilm(1, 'latest', 'al1vka', 6, '2026-09-28');

        $intro = (string) $this->messages()->spotlight(new \DateTimeImmutable('2026-09-29'))?->intro;

        self::assertStringContainsString('<b>❤️ Любимые фильмы - 10 из 10</b>', $intro);
        self::assertSame(5, substr_count((string) strstr($intro, '<blockquote expandable>'), '<a href='));
    }

    public function testSpotlightReturnsNullWhenNobodyPickedThisWeek(): void
    {
        $this->givenMembers('al1vka');
        $this->givenRound(1);
        $this->givenFilmRatedBy('arrival', []);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2026-09-21');

        self::assertNull($this->messages()->spotlight(new \DateTimeImmutable('2026-09-29')));
    }

    private function seedSummaryRound(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'koshmarus');
        $this->givenRound(1, '2025-01-06', '2025-03-10');
        $this->givenFilmRatedBy('unity', ['al1vka' => 8, 'christallisme' => 8, 'koshmarus' => 8]);
        $this->givenFilmRatedBy('flop', ['al1vka' => 2, 'christallisme' => 2]);
        $this->rounds->addFilm(1, 'unity', 'al1vka', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'flop', 'christallisme', 2, '2025-01-13');
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
