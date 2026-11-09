<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

use App\Statistics\ScoredFilm;
use App\Statistics\TasteMatch;

final readonly class MemberYear
{
    /**
     * @param ScoredFilm[]    $favorites
     * @param ScoredFilm[]    $leastFavorites
     * @param ScoredFilm[]    $hotTakes
     * @param TagFigure[]     $genres
     * @param YearPick[]      $picks
     * @param YearPick[]      $bestPicks
     * @param TasteMatch[]    $closest
     * @param TasteMatch[]    $farthest
     * @param LetterboxdGap[] $letterboxd
     */
    public function __construct(
        public string $username,
        public string $displayName,
        public int $watched,
        public int $minutes,
        public int $rank,
        public ?float $average,
        public ?float $leaning,
        public int $reviews,
        public array $favorites,
        public array $leastFavorites,
        public array $hotTakes,
        public array $genres,
        public array $picks,
        public ?float $picksAverage,
        public array $bestPicks,
        public array $closest,
        public array $farthest,
        public array $letterboxd,
    ) {
    }
}
