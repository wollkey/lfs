<?php

declare(strict_types=1);

namespace App\Statistics;

use App\Domain\MemberStatus;

final readonly class MemberStats
{
    public function __construct(
        public string $username,
        public string $displayName,
        public int $watched,
        public ?float $averageGiven,
        public MemberStatus $status,
    ) {
    }
}
