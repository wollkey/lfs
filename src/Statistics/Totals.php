<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class Totals
{
    public function __construct(
        public int $films,
        public int $ratings,
        public int $members,
        public ?int $currentRound,
    ) {
    }
}
