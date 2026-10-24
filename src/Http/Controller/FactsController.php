<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Statistics\ClubFacts;
use App\Statistics\Statistics;

final readonly class FactsController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    public function __invoke(): ClubFacts
    {
        return $this->stats->facts();
    }
}
