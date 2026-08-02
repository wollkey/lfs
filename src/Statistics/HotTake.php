<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class HotTake
{
    public function __construct(
        public string $displayName,
        public string $filmTitle,
        public int $score,
        public float $filmAverage,
    ) {
    }
}
