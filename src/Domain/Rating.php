<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Rating
{
    public function __construct(
        public string $filmSlug,
        public string $username,
        public Score $score,
    ) {
    }
}
