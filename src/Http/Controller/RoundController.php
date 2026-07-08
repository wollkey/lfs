<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Statistics\Statistics;

final readonly class RoundController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    /**
     * @return array{rounds: list<mixed>}
     */
    public function __invoke(): array
    {
        return ['rounds' => $this->stats->rounds()];
    }
}
