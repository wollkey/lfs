<?php

declare(strict_types=1);

namespace App\Letterboxd\Dto;

final readonly class ParsedRating
{
    public function __construct(
        public string $username,
        public int $rating,
    ) {
    }
}
