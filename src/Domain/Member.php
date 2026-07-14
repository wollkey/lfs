<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Member
{
    public function __construct(
        public string $username,
        public string $displayName,
        public MemberStatus $status = MemberStatus::Active,
        public ?int $position = null,
    ) {
    }
}
