<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class MemberSpotlight
{
    /**
     * @param ScoredFilm[] $favorites
     * @param ListedFilm[] $bestPicks
     * @param ListedFilm[] $worstPicks
     * @param ScoredFilm[] $hotTakes
     */
    public function __construct(
        public string $username,
        public string $displayName,
        public ?int $telegramUserId,
        public ListedFilm $pick,
        public ?ListedFilm $previousPick,
        public array $favorites,
        public array $bestPicks,
        public array $worstPicks,
        public ?float $picksAverage,
        public ?float $clubPicksAverage,
        public int $ratings,
        public ?float $averageGiven,
        public ?float $leaning,
        public array $hotTakes,
    ) {
    }
}
