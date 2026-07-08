<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Statistics\Statistics;

final readonly class MemberController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    /**
     * @return array{members: list<mixed>}
     */
    public function __invoke(): array
    {
        return ['members' => $this->stats->membersWithStats()];
    }
}
