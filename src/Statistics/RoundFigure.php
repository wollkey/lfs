<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class RoundFigure
{
    public function __construct(
        public int $number,
        public int|float $value,
    ) {
    }
}
