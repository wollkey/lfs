<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class Overview
{
    /**
     * Each record is the whole tie: all entries sharing the top value.
     *
     * @param RatedFilm[]   $bestFilm
     * @param RatedFilm[]   $worstFilm
     * @param RatedFilm[]   $mostDivisive
     * @param RatedFilm[]   $mostAgreed
     * @param MemberStats[] $mostActiveMember
     * @param MemberStats[] $bestCurator
     */
    public function __construct(
        public Totals $totals,
        public array $bestFilm,
        public array $worstFilm,
        public array $mostDivisive,
        public array $mostAgreed,
        public array $mostActiveMember,
        public array $bestCurator,
    ) {
    }
}
