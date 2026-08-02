<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class RoundSummary
{
    /**
     * @param ListedFilm[]     $films
     * @param RatedFilm[]      $best
     * @param RatedFilm[]      $worst
     * @param RatedFilm[]      $divisive
     * @param RatedFilm[]      $agreed
     * @param MemberActivity[] $activity
     * @param MemberActivity[] $mostRatings
     * @param MemberActivity[] $mostReviews
     * @param MemberActivity[] $mostGenerous
     * @param MemberActivity[] $harshest
     * @param RoundPick[]      $bestPicks
     * @param HotTake[]        $hotTakes
     */
    public function __construct(
        public int $number,
        public ?string $startedOn,
        public ?string $endedOn,
        public int $filmCount,
        public int $ratingsTotal,
        public int $reviewsTotal,
        public ?float $average,
        public array $films,
        public array $best,
        public array $worst,
        public array $divisive,
        public array $agreed,
        public array $activity,
        public array $mostRatings,
        public array $mostReviews,
        public array $mostGenerous,
        public array $harshest,
        public array $bestPicks,
        public array $hotTakes,
    ) {
    }
}
