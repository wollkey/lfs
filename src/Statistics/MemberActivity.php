<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class MemberActivity
{
    public function __construct(
        public string $username,
        public string $displayName,
        public int $ratings,
        public int $reviews,
        public ?float $average,
    ) {
    }
}
