<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Statistics\ListedFilm;
use App\Statistics\ScoredFilm;
use App\Statistics\Statistics;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Statistics::class)]
final class MemberSpotlightTest extends IntegrationTestCase
{
    public function testSpotlightsThePickerOfTheLatestFilm(): void
    {
        $this->seedPicks();

        $spotlight = $this->statistics(quorum: 3)->pickerSpotlight('2026-09-28', '2026-10-04');

        self::assertNotNull($spotlight);
        self::assertSame('al1vka', $spotlight->username);
        self::assertSame(42, $spotlight->telegramUserId);
        self::assertSame('interstellar', $spotlight->pick->slug);
        self::assertSame('tenet', $spotlight->previousPick?->slug);
    }

    public function testCollectsTheFactsAboutThePicker(): void
    {
        $this->seedPicks();

        $spotlight = $this->statistics(quorum: 3)->pickerSpotlight('2026-09-28', '2026-10-04');

        self::assertNotNull($spotlight);
        self::assertSame(['arrival', 'stalker'], $this->slugs($spotlight->favorites));
        self::assertSame(['arrival'], $this->slugs($spotlight->bestPicks));
        self::assertSame(['tenet'], $this->slugs($spotlight->worstPicks));
        self::assertSame(6.5, $spotlight->picksAverage);
        self::assertSame(7.4, $spotlight->clubPicksAverage);
        self::assertSame(3, $spotlight->ratings);
        self::assertSame(8.7, $spotlight->averageGiven);
        self::assertSame(1.8, $spotlight->leaning);
        self::assertSame(['arrival'], $this->slugs($spotlight->hotTakes));
    }

    public function testThisWeeksPickNeverCountsAsABestOrWorstPick(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('arrival', ['al1vka' => 9, 'christallisme' => 7]);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2026-09-28');

        $spotlight = $this->statistics(quorum: 1)->pickerSpotlight('2026-09-28', '2026-10-04');

        self::assertNotNull($spotlight);
        self::assertSame([], $spotlight->bestPicks);
        self::assertSame([], $spotlight->worstPicks);
        self::assertNull($spotlight->previousPick);
    }

    public function testASinglePastPickIsTheBestButNotAlsoTheWorst(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('arrival', ['al1vka' => 9, 'christallisme' => 7]);
        $this->givenFilmRatedBy('interstellar', []);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2026-09-07');
        $this->rounds->addFilm(1, 'interstellar', 'al1vka', 2, '2026-09-28');

        $spotlight = $this->statistics(quorum: 1)->pickerSpotlight('2026-09-28', '2026-10-04');

        self::assertNotNull($spotlight);
        self::assertSame(['arrival'], $this->slugs($spotlight->bestPicks));
        self::assertSame([], $spotlight->worstPicks);
    }

    public function testSkipsWhenTheLatestFilmWasPickedBeforeThisWeek(): void
    {
        $this->seedPicks();

        self::assertNull($this->statistics(quorum: 3)->pickerSpotlight('2026-10-05', '2026-10-11'));
    }

    public function testSkipsWithoutAnyFilms(): void
    {
        self::assertNull($this->statistics()->pickerSpotlight('2026-09-28', '2026-10-04'));
    }

    private function seedPicks(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->members->linkTelegram('al1vka', 42);
        $this->givenRound(1);
        $this->givenFilmRatedBy('arrival', ['al1vka' => 10, 'christallisme' => 6, 'wollkey' => 8]);
        $this->givenFilmRatedBy('tenet', ['al1vka' => 6, 'christallisme' => 4, 'wollkey' => 5]);
        $this->givenFilmRatedBy('stalker', ['al1vka' => 10, 'christallisme' => 9, 'wollkey' => 9]);
        $this->givenFilmRatedBy('interstellar', []);
        $this->rounds->addFilm(1, 'arrival', 'al1vka', 1, '2026-09-07');
        $this->rounds->addFilm(1, 'tenet', 'al1vka', 2, '2026-09-14');
        $this->rounds->addFilm(1, 'stalker', 'christallisme', 3, '2026-09-21');
        $this->rounds->addFilm(1, 'interstellar', 'al1vka', 4, '2026-09-28');
    }

    /**
     * @param list<ListedFilm|ScoredFilm> $films
     *
     * @return list<string>
     */
    private function slugs(array $films): array
    {
        return array_map(static fn (ListedFilm|ScoredFilm $f): string => $f->slug, $films);
    }
}
