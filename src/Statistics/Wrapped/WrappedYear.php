<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

final readonly class WrappedYear
{
    /**
     * @param MemberYear[] $members
     */
    public function __construct(
        public int $year,
        public ClubYear $club,
        public array $members,
    ) {
    }
}
