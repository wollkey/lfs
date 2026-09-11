<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class MemberReview
{
    public function __construct(
        public string $username,
        public string $displayName,
        public ?int $score,
        public string $body,
    ) {
    }
}
