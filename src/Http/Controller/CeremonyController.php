<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\NotFound;
use App\Statistics\Ceremony\Ceremony;
use App\Statistics\Ceremony\CeremonyYear;

final readonly class CeremonyController
{
    public function __construct(
        private Ceremony $ceremony,
    ) {
    }

    public function __invoke(string $year): CeremonyYear
    {
        return $this->ceremony->year((int) $year) ?? throw new NotFound("No films rated in {$year}.");
    }
}
