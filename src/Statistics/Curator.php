<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class Curator
{
    public function __construct(
        public string $username,
        public string $displayName,
        public int $picks,
        public float $average,
    ) {
    }
}
