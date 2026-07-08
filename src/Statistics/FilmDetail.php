<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class FilmDetail
{
    /**
     * @param MemberScore[] $ratings
     * @param MemberName[]  $notWatched
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?int $round,
        public ?string $pickedBy,
        public ?float $average,
        public ?int $spread,
        public array $ratings,
        public array $notWatched,
    ) {
    }
}
