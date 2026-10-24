<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class FilmFigure
{
    public function __construct(
        public string $slug,
        public string $title,
        public int|float $value,
    ) {
    }
}
