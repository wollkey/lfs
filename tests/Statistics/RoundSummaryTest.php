<?php

declare(strict_types=1);

namespace App\Tests\Statistics;

use App\Statistics\MemberActivity;
use App\Statistics\RatedFilm;
use App\Statistics\Statistics;
use App\Tests\Common\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Statistics::class)]
final class RoundSummaryTest extends IntegrationTestCase
{
    public function testReturnsNullWhenTheRoundHasNoFilms(): void
    {
        $this->givenRound(1);

        self::assertNull($this->statistics(quorum: 2)->roundSummary(1));
    }

    public function testSummarisesFilmsActivityAndAwards(): void
    {
        $this->seedRound();

        $summary = $this->statistics(quorum: 2)->roundSummary(1);

        self::assertNotNull($summary);
        self::assertSame(1, $summary->number);
        self::assertSame('2025-01-06', $summary->startedOn);
        self::assertSame('2025-03-10', $summary->endedOn);
        self::assertSame(3, $summary->filmCount);
        self::assertSame(8, $summary->ratingsTotal);
        self::assertSame(3, $summary->reviewsTotal);
        self::assertSame(5.1, $summary->average);

        self::assertSame(['Unity', 'Split', 'Flop'], array_map(static fn ($f) => $f->title, $summary->films));

        self::assertSame(['Unity'], $this->titles($summary->best));
        self::assertSame(['Flop'], $this->titles($summary->worst));
        self::assertSame(['Split'], $this->titles($summary->divisive));
        self::assertSame(['Unity'], $this->titles($summary->agreed));
    }

    public function testMostActiveRatersAndReviewersHandleTies(): void
    {
        $this->seedRound();

        $summary = $this->statistics(quorum: 2)->roundSummary(1);

        self::assertNotNull($summary);
        self::assertSame(['Al1vka', 'Christallisme'], $this->names($summary->mostRatings));
        self::assertSame(['Al1vka'], $this->names($summary->mostReviews));
        self::assertSame(['Christallisme'], $this->names($summary->mostGenerous));
        self::assertSame(['Al1vka'], $this->names($summary->harshest));

        self::assertCount(1, $summary->bestPicks);
        self::assertSame('Al1vka', $summary->bestPicks[0]->pickedBy);
        self::assertSame('Unity', $summary->bestPicks[0]->filmTitle);
        self::assertSame(8.0, $summary->bestPicks[0]->average);
    }

    public function testHotTakesAreTheRatingsFurthestFromTheFilmAverage(): void
    {
        $this->seedRound();

        $summary = $this->statistics(quorum: 2)->roundSummary(1);

        self::assertNotNull($summary);
        self::assertCount(2, $summary->hotTakes);

        self::assertSame('Al1vka', $summary->hotTakes[0]->displayName);
        self::assertSame('Split', $summary->hotTakes[0]->filmTitle);
        self::assertSame(1, $summary->hotTakes[0]->score);
        self::assertSame(5.0, $summary->hotTakes[0]->filmAverage);

        self::assertSame('Christallisme', $summary->hotTakes[1]->displayName);
        self::assertSame(9, $summary->hotTakes[1]->score);
    }

    public function testActivityCountsRatingsAndWrittenReviewsPerMember(): void
    {
        $this->seedRound();

        $summary = $this->statistics(quorum: 2)->roundSummary(1);

        self::assertNotNull($summary);
        $byName = [];
        foreach ($summary->activity as $member) {
            $byName[$member->displayName] = $member;
        }

        self::assertSame([3, 2], [$byName['Al1vka']->ratings, $byName['Al1vka']->reviews]);
        self::assertSame([3, 1], [$byName['Christallisme']->ratings, $byName['Christallisme']->reviews]);
        self::assertSame([2, 0], [$byName['Koshmarus']->ratings, $byName['Koshmarus']->reviews]);
    }

    private function seedRound(): void
    {
        $this->givenMembers('al1vka', 'christallisme', 'koshmarus');
        $this->givenRound(1, '2025-01-06', '2025-03-10');

        $this->givenFilmRatedBy('unity', ['al1vka' => 8, 'christallisme' => 8, 'koshmarus' => 8]);
        $this->givenFilmRatedBy('flop', ['al1vka' => 2, 'christallisme' => 2, 'koshmarus' => 3]);
        $this->givenFilmRatedBy('split', ['al1vka' => 1, 'christallisme' => 9]);

        $this->rounds->addFilm(1, 'unity', 'al1vka', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'flop', 'christallisme', 2, '2025-01-13');
        $this->rounds->addFilm(1, 'split', 'koshmarus', 3, '2025-01-20');

        $this->givenReview('unity', 'al1vka');
        $this->givenReview('flop', 'al1vka');
        $this->givenReview('unity', 'christallisme');
    }

    private function givenReview(string $slug, string $username): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ratings SET review = :review WHERE film_slug = :slug AND member_username = :user',
        );
        $stmt->execute(['review' => 'text', 'slug' => $slug, 'user' => $username]);
    }

    /**
     * @param RatedFilm[] $films
     *
     * @return string[]
     */
    private function titles(array $films): array
    {
        return array_map(static fn (RatedFilm $f) => $f->title, $films);
    }

    /**
     * @param MemberActivity[] $members
     *
     * @return string[]
     */
    private function names(array $members): array
    {
        return array_map(static fn (MemberActivity $m) => $m->displayName, $members);
    }
}
