<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class RoundView
{
    /**
     * @param ListedFilm[] $films
     */
    public function __construct(
        public int $number,
        public ?string $startedOn,
        public ?string $endedOn,
        public ?float $average,
        public ?RatedFilm $winner,
        public ?RatedFilm $worst,
        public array $films,
    ) {
    }
}
