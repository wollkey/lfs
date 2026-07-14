<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class Overview
{
    public function __construct(
        public Totals $totals,
        public ?RatedFilm $bestFilm,
        public ?RatedFilm $worstFilm,
        public ?RatedFilm $mostDivisive,
        public ?RatedFilm $mostAgreed,
        public ?MemberStats $mostActiveMember,
        public ?MemberStats $bestCurator,
    ) {
    }
}
