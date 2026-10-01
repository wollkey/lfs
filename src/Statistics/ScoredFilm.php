<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class ScoredFilm
{
    public function __construct(
        public string $slug,
        public string $title,
        public int $score,
        public float $average,
    ) {
    }
}
