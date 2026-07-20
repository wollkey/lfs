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
     * @return array{
     *     members: list<mixed>,
     *     picks: array<string, mixed>,
     *     missed: array<string, mixed>,
     *     currentRound: int|null,
     *  }
     */
    public function __invoke(): array
    {
        return [
            'members' => $this->stats->membersWithStats(),
            'picks' => $this->stats->picksByMember(),
            'missed' => $this->stats->missedByMember(),
            'currentRound' => $this->stats->currentRound(),
        ];
    }
}
