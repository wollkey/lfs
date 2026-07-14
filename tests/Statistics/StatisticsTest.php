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
    public function testBestFilmIgnoresFilmsBelowQuorum(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'vika');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('solaris', ['wollkey' => 10]);

        $best = $this->statistics(quorum: 2)->bestFilm();

        self::assertSame('stalker', $best?->slug);
    }

    public function testWorstFilmRespectsQuorum(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'vika');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('morbius', ['wollkey' => 3, 'lenka' => 4, 'vika' => 3]);
        $this->givenFilmRatedBy('flop', ['wollkey' => 1]);

        $worst = $this->statistics(quorum: 2)->worstFilm();

        self::assertSame('morbius', $worst?->slug);
    }

    public function testMostDivisiveFilmHasLargestSpread(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'vika');
        $this->givenFilmRatedBy('mother', ['wollkey' => 2, 'lenka' => 9, 'vika' => 5]);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8, 'vika' => 9]);

        $divisive = $this->statistics(quorum: 2)->mostDivisive();

        self::assertSame('mother', $divisive?->slug);
        self::assertSame(7, $divisive->spread);
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

    public function testFormerMemberIsNeverListedAsNotWatched(): void
    {
        $this->givenMembers('wollkey', 'vika');
        $this->givenMember('justdanya', MemberStatus::Former);
        $this->givenFilmRatedBy('solaris', ['wollkey' => 10]);

        $detail = $this->statistics()->filmDetail('solaris');

        $notWatched = array_map(static fn ($m) => $m->username, $detail->notWatched);
        self::assertArraysHaveIdenticalValuesIgnoringOrder(['vika'], $notWatched);   // ровно vika: не бывший, не смотревший
    }

    public function testFilmListWithRatingsGroupsScoresPerFilm(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'vika');
        $this->givenRound(1);
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8, 'vika' => 9]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey');

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
        $this->givenMembers('newbie');

        $members = $this->indexByUsername($this->statistics()->membersWithStats());

        self::assertSame(0, $members['newbie']->watched);
        self::assertNull($members['newbie']->averageGiven);   // LEFT JOIN → нет оценок → null
    }

    public function testRoundWinnerIsFilmWithHighestAverage(): void
    {
        $this->givenMembers('wollkey', 'lenka', 'vika');
        $this->givenRound(1, '2024-01-05', '2024-03-10');
        $this->givenFilmRatedBy('stalker', ['wollkey' => 9, 'lenka' => 8, 'vika' => 9]);
        $this->givenFilmRatedBy('mother', ['wollkey' => 2, 'lenka' => 9, 'vika' => 5]);
        $this->rounds->addFilm(1, 'stalker', 'wollkey');
        $this->rounds->addFilm(1, 'mother', 'lenka');

        $rounds = $this->statistics(quorum: 2)->rounds();

        self::assertSame('stalker', $rounds[0]->winner?->slug);
    }

    public function testRoundWithoutRatingsHasNoWinner(): void
    {
        $this->givenMembers('wollkey');
        $this->givenRound(2);
        $this->givenFilmRatedBy('unseen', []);   // фильм в круге, но никто не оценил
        $this->rounds->addFilm(2, 'unseen', 'wollkey');

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
