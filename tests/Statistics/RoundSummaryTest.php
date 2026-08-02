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
        self::assertSame(['Anna', 'Boris'], $this->names($summary->mostRatings));
        self::assertSame(['Anna'], $this->names($summary->mostReviews));
        self::assertSame(['Boris'], $this->names($summary->mostGenerous));
        self::assertSame(['Anna'], $this->names($summary->harshest));

        self::assertCount(1, $summary->bestPicks);
        self::assertSame('Anna', $summary->bestPicks[0]->pickedBy);
        self::assertSame('Unity', $summary->bestPicks[0]->filmTitle);
        self::assertSame(8.0, $summary->bestPicks[0]->average);
    }

    public function testHotTakesAreTheRatingsFurthestFromTheFilmAverage(): void
    {
        $this->seedRound();

        $summary = $this->statistics(quorum: 2)->roundSummary(1);

        self::assertNotNull($summary);
        self::assertCount(2, $summary->hotTakes);

        self::assertSame('Anna', $summary->hotTakes[0]->displayName);
        self::assertSame('Split', $summary->hotTakes[0]->filmTitle);
        self::assertSame(1, $summary->hotTakes[0]->score);
        self::assertSame(5.0, $summary->hotTakes[0]->filmAverage);

        self::assertSame('Boris', $summary->hotTakes[1]->displayName);
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

        self::assertSame([3, 2], [$byName['Anna']->ratings, $byName['Anna']->reviews]);
        self::assertSame([3, 1], [$byName['Boris']->ratings, $byName['Boris']->reviews]);
        self::assertSame([2, 0], [$byName['Clara']->ratings, $byName['Clara']->reviews]);
    }

    private function seedRound(): void
    {
        $this->givenMembers('anna', 'boris', 'clara');
        $this->givenRound(1, '2025-01-06', '2025-03-10');

        $this->givenFilmRatedBy('unity', ['anna' => 8, 'boris' => 8, 'clara' => 8]);
        $this->givenFilmRatedBy('flop', ['anna' => 2, 'boris' => 2, 'clara' => 3]);
        $this->givenFilmRatedBy('split', ['anna' => 1, 'boris' => 9]);

        $this->rounds->addFilm(1, 'unity', 'anna', 1, '2025-01-06');
        $this->rounds->addFilm(1, 'flop', 'boris', 2, '2025-01-13');
        $this->rounds->addFilm(1, 'split', 'clara', 3, '2025-01-20');

        $this->givenReview('unity', 'anna');
        $this->givenReview('flop', 'anna');
        $this->givenReview('unity', 'boris');
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
