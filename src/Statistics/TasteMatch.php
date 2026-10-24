<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class TasteMatch
{
    public function __construct(
        public string $username,
        public string $displayName,
        public int $agreement,
        public int $shared,
    ) {
    }
}
