<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class MemberFigure
{
    public function __construct(
        public string $username,
        public string $displayName,
        public int|float $value,
    ) {
    }
}
