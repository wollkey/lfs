<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class RatedFilm
{
    public function __construct(
        public string $slug,
        public string $title,
        public float $average,
        public int $votes,
        public int $spread,
    ) {
    }
}
