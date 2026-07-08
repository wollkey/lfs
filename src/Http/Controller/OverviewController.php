<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Statistics\Overview;
use App\Statistics\Statistics;

final readonly class OverviewController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    public function __invoke(): Overview
    {
        return $this->stats->overview();
    }
}
