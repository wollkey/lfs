<?php

declare(strict_types=1);

namespace App\Statistics\Ceremony;

final readonly class CeremonyYear
{
    /**
     * @param list<Nomination> $nominations
     */
    public function __construct(
        public int $year,
        public array $nominations,
    ) {
    }
}
