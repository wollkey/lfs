<?php

declare(strict_types=1);

namespace App\Letterboxd\Dto;

use App\Domain\FilmDetails;

final readonly class ParsedFilm
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $posterUrl = null,
        public ?FilmDetails $details = null,
    ) {
    }
}
