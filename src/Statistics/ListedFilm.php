<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class ListedFilm
{
    /**
     * @param MemberScore[]|null $ratings
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?float $average,
        public int $votes,
        public ?int $round,
        public ?string $pickedBy,
        public ?int $position,
        public ?array $ratings = null,
    ) {
    }
}
