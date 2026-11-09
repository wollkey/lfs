<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

final readonly class YearPick
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $username,
        public string $displayName,
        public ?float $average,
        public int $votes,
    ) {
    }
}
