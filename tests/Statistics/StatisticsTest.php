<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Domain\MemberStatus;
use App\Statistics\ListedFilm;
use App\Statistics\MemberStats;
use App\Statistics\Statistics;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Statistics::class)]
final class StatisticsTest extends IntegrationTestCase
{
    public function testFilmsPickedBetweenReturnsFilmsWithinTheInclusiveWindow(): void
    {
        $this->givenMembers('al1vka');
        $this->givenRound(1);
        $this->givenFilmRatedBy('before', ['al1vka' => 5]);
        $this->givenFilmRatedBy('start', ['al1vka' => 6]);
        $this->givenFilmRatedBy('end', ['al1vka' => 7]);
        $this->givenFilmRatedBy('after', ['al1vka' => 8]);
        $this->rounds->addFilm(1, 'before', 'al1vka', 1, '2025-08-03');
        $this->rounds->addFilm(1, 'start', 'al1vka', 2, '2025-08-04');
        $this->rounds->addFilm(1, 'end', 'al1vka', 3, '2025-08-10');
        $this->rounds->addFilm(1, 'after', 'al1vka', 4, '2025-08-11');

        $films = $this->statistics(quorum: 1)->filmsPickedBetween('2025-08-04', '2025-08-10');

        $titles = array_map(static fn (ListedFilm $f): string => $f->title, $films);
        self::assertSame(['Start', 'End'], $titles);
    }

    public function testLatestRatedFilmPicksTheNewestFilmThatReachedQuorum(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1);
        $this->givenFilmRatedBy('qualified', ['al1vka' => 6, 'christallisme' => 8]);
        $this->givenFilmRatedBy('newest', ['al1vka' => 7]);
        $this->rounds->addFilm(1, 'qualified', 'al1vka', 1, '2026-02-02');
        $this->rounds->addFilm(1, 'newest', 'christallisme', 2, '2026-02-09');

        $film = $this->statistics(quorum: 2)->latestRatedFilm();

        self::assertSame('qualified', $film?->slug);
    }

    public function testBestFilmIgnoresFilmsBelowQuorum(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'vika');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('solaris', ['wollkey' => 10]);

        $best = $this->statistics(quorum: 2)->overview()->bestFilm;

        self::assertSame(['stalker'], array_map(static fn ($f) => $f->slug, $best));
    }

    public function testWorstFilmRespectsQuorum(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'vika');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('morbius', ['wollkey' => 3, 'lenka_penka' => 4, 'vika' => 3]);
        $this->givenFilmRatedBy('flop', ['wollkey' => 1]);

        $worst = $this->statistics(quorum: 2)->overview()->worstFilm;

        self::assertSame(['morbius'], array_map(static fn ($f) => $f->slug, $worst));
    }

    public function testMostDivisiveFilmHasLargestSpread(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'vika');
        $this->givenFilmRatedBy('mother', ['wollkey' => 2, 'lenka_penka' => 9, 'vika' => 5]);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 8, 'vika' => 9]);

        $divisive = $this->statistics(quorum: 2)->overview()->mostDivisive;

        self::assertSame(['mother'], array_map(static fn ($f) => $f->slug, $divisive));
        self::assertSame(7, $divisive[0]->spread);
    }

    public function testFormerMemberRatingAppearsInFilmDetail(): void
    {
        $this->givenMembers('wollkey');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'justdanya' => 8]);

        $detail = $this->statistics()->filmDetail('stalker');

        $usernames = array_map(static fn ($m) => $m->username, $detail->ratings);
        self::assertContains('justdanya', $usernames);
    }

    public function testFilmDetailCarriesReviewsWithAndWithoutAScore(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'christallisme');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 7]);
        $this->givenReview('stalker', 'wollkey', 'Пересмотр дался легче.');
        $this->givenReview('stalker', 'christallisme', 'Оценку поставлю позже.');

        $detail = $this->statistics()->filmDetail('stalker');

        self::assertCount(2, $detail->ratings);
        self::assertSame(
            [['wollkey', 9], ['christallisme', null]],
            array_map(static fn ($r) => [$r->username, $r->score], $detail->reviews),
        );
    }

    public function testFormerMemberIsNeverListedAsNotWatched(): void
    {
        $this->givenMembers('wollkey', 'vika');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('solaris', ['wollkey' => 10]);

        $detail = $this->statistics()->filmDetail('solaris');

        $notWatched = array_map(static fn ($m) => $m->username, $detail->notWatched);
        self::assertArraysHaveIdenticalValuesIgnoringOrder(['vika'], $notWatched);   // exactly vika: not former, has not watched it
    }

    public function testFilmListWithRatingsGroupsScoresPerFilm(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'vika');
        $this->givenRound(1);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 8, 'vika' => 9]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1, '2025-01-06');

        $films = $this->indexBySlug($this->statistics()->films(withRatings: true));

        self::assertSame(1, $films['stalker']->round);
        self::assertSame(3, $films['stalker']->votes);
        self::assertCount(3, $films['stalker']->ratings);
    }

    public function testCompactFilmListOmitsRatings(): void
    {
        $this->givenMembers('wollkey');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9]);

        $films = $this->statistics()->films();

        self::assertNull($films[0]->ratings);
    }

    public function testMemberStatsExposeWatchCountAverageAndStatus(): void
    {
        $this->givenMembers('wollkey');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'justdanya' => 7]);
        $this->givenFilmRatedBy('mother', ['wollkey' => 3]);

        $members = $this->indexByUsername($this->statistics()->membersWithStats());

        self::assertSame(2, $members['wollkey']->watched);
        self::assertSame(6.0, $members['wollkey']->averageGiven);   // (9+3)/2
        self::assertSame(MemberStatus::Active, $members['wollkey']->status);
        self::assertSame(MemberStatus::Former, $members['justdanya']->status);
    }

    public function testMemberWithoutRatingsHasZeroWatchedAndNullAverage(): void
    {
        $this->givenMembers('vans_von_trier');

        $members = $this->indexByUsername($this->statistics()->membersWithStats());

        self::assertSame(0, $members['vans_von_trier']->watched);
        self::assertNull($members['vans_von_trier']->averageGiven);   // LEFT JOIN => no ratings => null
    }

    public function testRoundWinnerIsFilmWithHighestAverage(): void
    {
        $this->givenMembers('wollkey', 'lenka_penka', 'vika');
        $this->givenRound(1, '2024-01-05', '2024-03-10');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka_penka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('mother', ['wollkey' => 2, 'lenka_penka' => 9, 'vika' => 5]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'mother', 'lenka_penka', 2, '2025-01-13');

        $rounds = $this->statistics(quorum: 2)->rounds();

        self::assertSame('stalker', $rounds[0]->winner?->slug);
    }

    public function testRoundWithoutRatingsHasNoWinner(): void
    {
        $this->givenMembers('wollkey');
        $this->givenRound(2);
        $this->givenFilmRatedBy('unseen', []);
        $this->rounds->addFilm(2, 'unseen', 'wollkey', 1, '2025-06-30');

        $rounds = $this->statistics()->rounds();

        self::assertNull($rounds[0]->winner);
    }

    /**
     * @param ListedFilm[] $films
     *
     * @return array<string, ListedFilm>
     */
    private function indexBySlug(array $films): array
    {
        $out = [];
        foreach ($films as $f) {
            $out[$f->slug] = $f;
        }

        return $out;
    }

    /**
     * @param MemberStats[] $members
     *
     * @return array<string, MemberStats>
     */
    private function indexByUsername(array $members): array
    {
        $out = [];
        foreach ($members as $m) {
            $out[$m->username] = $m;
        }

        return $out;
    }
}
