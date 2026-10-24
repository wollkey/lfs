<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class ClubFacts
{
    /**
     * @param MemberFigure[] $harshestCritics
     * @param MemberFigure[] $kindestCritics
     * @param RoundFigure[]  $bestRounds
     * @param RoundFigure[]  $worstRounds
     * @param RoundFigure[]  $longestRounds
     * @param FilmFigure[]   $mostTens
     * @param FilmFigure[]   $mostOnes
     * @param FilmName[]     $tenAndOne
     * @param MemberName[]   $completionists
     * @param FilmFigure[]   $leastWatched
     * @param MemberFigure[] $mostWatchedPickers
     * @param MemberFigure[] $mostReviews
     * @param HotTake[]      $hotTakes
     */
    public function __construct(
        public array $harshestCritics,
        public array $kindestCritics,
        public array $bestRounds,
        public array $worstRounds,
        public array $longestRounds,
        public int $tens,
        public array $mostTens,
        public int $ones,
        public array $mostOnes,
        public array $tenAndOne,
        public array $completionists,
        public array $leastWatched,
        public array $mostWatchedPickers,
        public array $mostReviews,
        public array $hotTakes,
    ) {
    }
}
