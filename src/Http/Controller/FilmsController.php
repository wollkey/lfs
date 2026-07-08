<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Statistics\Statistics;

final readonly class FilmsController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    /**
     * @return array{films: list<mixed>}
     */
    public function __invoke(): array
    {
        $withRatings = ($_GET['withRatings'] ?? '') === '1';

        return ['films' => $this->stats->films($withRatings)];
    }
}
