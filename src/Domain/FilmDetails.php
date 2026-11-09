<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class FilmDetails
{
    /**
     * @param list<string> $directors
     * @param list<string> $genres
     * @param list<string> $countries
     */
    public function __construct(
        public ?int $releaseYear,
        public ?int $runtime,
        public ?float $letterboxdRating,
        public array $directors = [],
        public array $genres = [],
        public array $countries = [],
    ) {
    }
}
