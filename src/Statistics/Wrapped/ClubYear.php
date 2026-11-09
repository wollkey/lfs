<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

use App\Statistics\FilmFigure;
use App\Statistics\RatedFilm;

final readonly class ClubYear
{
    /**
     * @param RatedFilm[]     $best
     * @param RatedFilm[]     $worst
     * @param RatedFilm[]     $divisive
     * @param YearPick[]      $bestPicks
     * @param TagFigure[]     $directors
     * @param TagFigure[]     $genres
     * @param TagFigure[]     $countries
     * @param FilmFigure[]    $oldest
     * @param LetterboxdGap[] $aboveLetterboxd
     * @param LetterboxdGap[] $belowLetterboxd
     */
    public function __construct(
        public int $films,
        public int $minutes,
        public int $ratings,
        public int $reviews,
        public int $countryCount,
        public array $best,
        public array $worst,
        public array $divisive,
        public array $bestPicks,
        public array $directors,
        public array $genres,
        public array $countries,
        public array $oldest,
        public array $aboveLetterboxd,
        public array $belowLetterboxd,
    ) {
    }
}
