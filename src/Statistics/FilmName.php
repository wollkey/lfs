<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class FilmName
{
    public function __construct(
        public string $slug,
        public string $title,
    ) {
    }
}
