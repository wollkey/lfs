<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class RoundPick
{
    public function __construct(
        public string $pickedBy,
        public string $filmTitle,
        public float $average,
    ) {
    }
}
