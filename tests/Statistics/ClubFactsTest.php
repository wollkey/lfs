<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Domain\MemberStatus;
use App\Statistics\ClubFacts;
use App\Statistics\FilmFigure;
use App\Statistics\FilmName;
use App\Statistics\MemberFigure;
use App\Statistics\MemberName;
use App\Statistics\RoundFigure;
use App\Statistics\Statistics;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Statistics::class)]
final class ClubFactsTest extends IntegrationTestCase
{
    public function testNamesTheHarshestAndKindestCritics(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenFilmRatedBy('arrival', ['al1vka' => 9, 'christallisme' => 4, 'wollkey' => 7]);
        $this->givenFilmRatedBy('tenet', ['al1vka' => 8, 'christallisme' => 5]);

        $facts = $this->facts(minCriticRatings: 2);

        self::assertSame(['christallisme'], $this->usernames($facts->harshestCritics));
        self::assertSame(4.5, $facts->harshestCritics[0]->value);
        self::assertSame(['al1vka'], $this->usernames($facts->kindestCritics));
    }

    public function testComparesOnlyFinishedRounds(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenRound(1, '2026-01-01', '2026-03-01');
        $this->givenRound(2, '2026-03-02', '2026-04-01');
        $this->givenRound(3, '2026-04-02');
        $this->givenPick(1, 'arrival', 'al1vka', ['al1vka' => 9, 'christallisme' => 8]);
        $this->givenPick(2, 'tenet', 'al1vka', ['al1vka' => 5, 'christallisme' => 6]);
        $this->givenPick(3, 'stalker', 'al1vka', ['al1vka' => 10, 'christallisme' => 10]);

        $facts = $this->facts();

        self::assertEquals([new RoundFigure(1, 8.5)], $facts->bestRounds);
        self::assertEquals([new RoundFigure(2, 5.5)], $facts->worstRounds);
        self::assertEquals([new RoundFigure(1, 59)], $facts->longestRounds);
    }

    public function testASingleFinishedRoundIsNeitherTheBestNorTheWorst(): void
    {
        $this->givenMembers('al1vka');
        $this->givenRound(1, '2026-01-01', '2026-03-01');
        $this->givenPick(1, 'arrival', 'al1vka', ['al1vka' => 9]);

        $facts = $this->facts();

        self::assertSame([], $facts->bestRounds);
        self::assertSame([], $facts->worstRounds);
    }

    public function testCountsTensAndOnes(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenFilmRatedBy('dogville', ['al1vka' => 10, 'christallisme' => 10, 'wollkey' => 1]);
        $this->givenFilmRatedBy('tenet', ['al1vka' => 10, 'christallisme' => 1, 'wollkey' => 1]);
        $this->givenFilmRatedBy('arrival', ['al1vka' => 7]);

        $facts = $this->facts();

        self::assertSame(3, $facts->tens);
        self::assertEquals([new FilmFigure('dogville', 'Dogville', 2)], $facts->mostTens);
        self::assertSame(3, $facts->ones);
        self::assertEquals([new FilmFigure('tenet', 'Tenet', 2)], $facts->mostOnes);
        self::assertEquals([new FilmName('dogville', 'Dogville'), new FilmName('tenet', 'Tenet')], $facts->tenAndOne);
    }

    public function testCompletionistsRatedEveryFilmOfEveryFinishedRound(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenMember('wollkey', MemberStatus::Former);
        $this->givenRound(1, '2026-01-01', '2026-03-01');
        $this->givenRound(2, '2026-03-02');
        $this->givenPick(1, 'arrival', 'al1vka', ['al1vka' => 9, 'christallisme' => 8, 'wollkey' => 7]);
        $this->givenPick(1, 'tenet', 'christallisme', ['al1vka' => 6, 'wollkey' => 7]);
        $this->givenPick(2, 'stalker', 'al1vka', []);

        self::assertEquals([new MemberName('al1vka', 'Al1vka')], $this->facts()->completionists);
    }

    public function testFindsTheLeastWatchedFilmAndTheMostWatchedPicker(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenRound(1, '2026-01-01', '2026-03-01');
        $this->givenRound(2, '2026-03-02');
        $this->givenPick(1, 'arrival', 'al1vka', ['al1vka' => 9, 'christallisme' => 8, 'wollkey' => 7]);
        $this->givenPick(1, 'tenet', 'christallisme', ['al1vka' => 6]);
        $this->givenPick(2, 'stalker', 'wollkey', []);

        $facts = $this->facts();

        self::assertEquals([new FilmFigure('tenet', 'Tenet', 1)], $facts->leastWatched);
        self::assertEquals([new MemberFigure('al1vka', 'Al1vka', 3.0)], $facts->mostWatchedPickers);
    }

    public function testNamesWhoWroteTheMostReviews(): void
    {
        $this->givenMembers('al1vka', 'christallisme');
        $this->givenFilmRatedBy('arrival', []);
        $this->givenFilmRatedBy('tenet', []);
        $this->givenReview('arrival', 'al1vka');
        $this->givenReview('tenet', 'al1vka');
        $this->givenReview('arrival', 'christallisme');

        self::assertEquals([new MemberFigure('al1vka', 'Al1vka', 2)], $this->facts()->mostReviews);
    }

    public function testTheHotTakeIsTheScoreFurthestFromItsFilmsAverage(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'wollkey');
        $this->givenFilmRatedBy('dogville', ['al1vka' => 9, 'christallisme' => 9, 'wollkey' => 3]);
        $this->givenFilmRatedBy('tenet', ['al1vka' => 6, 'christallisme' => 7, 'wollkey' => 8]);

        $takes = $this->facts()->hotTakes;

        self::assertCount(1, $takes);
        self::assertSame('Wollkey', $takes[0]->displayName);
        self::assertSame('Dogville', $takes[0]->filmTitle);
        self::assertSame(3, $takes[0]->score);
    }

    public function testAnEmptyClubHasNoFacts(): void
    {
        $facts = $this->facts();

        self::assertSame([], $facts->harshestCritics);
        self::assertSame([], $facts->bestRounds);
        self::assertSame([], $facts->longestRounds);
        self::assertSame(0, $facts->tens);
        self::assertSame([], $facts->mostTens);
        self::assertSame([], $facts->tenAndOne);
        self::assertSame([], $facts->completionists);
        self::assertSame([], $facts->leastWatched);
        self::assertSame([], $facts->mostReviews);
        self::assertSame([], $facts->hotTakes);
    }

    private function facts(int $minCriticRatings = 1): ClubFacts
    {
        return new Statistics($this->pdo, quorum: 1, minCuratorPicks: 1, minCriticRatings: $minCriticRatings)->facts();
    }

    /**
     * @param array<string,int> $scores
     */
    private function givenPick(int $round, string $slug, string $pickedBy, array $scores): void
    {
        $this->givenFilmRatedBy($slug, $scores);
        $this->rounds->addFilm($round, $slug, $pickedBy, 1, '2026-01-01');
    }

    /**
     * @param MemberFigure[] $members
     *
     * @return string[]
     */
    private function usernames(array $members): array
    {
        return array_map(static fn (MemberFigure $m) => $m->username, $members);
    }
}
