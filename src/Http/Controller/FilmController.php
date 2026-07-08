<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\NotFound;
use App\Statistics\FilmDetail;
use App\Statistics\Statistics;

final readonly class FilmController
{
    public function __construct(
        private Statistics $stats,
    ) {
    }

    public function __invoke(string $slug): FilmDetail
    {
        $detail = $this->stats->filmDetail($slug);

        if ($detail === null) {
            throw new NotFound("Film '{$slug}' not found.");
        }

        return $detail;
    }
}
