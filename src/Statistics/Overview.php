<?php

declare(strict_types=1);

namespace App\Statistics;

final readonly class Overview
{
    /**
     * Each film record is the whole tie: all films sharing the top value.
     *
     * @param RatedFilm[] $bestFilm
     * @param RatedFilm[] $worstFilm
     * @param RatedFilm[] $mostDivisive
     * @param RatedFilm[] $mostAgreed
     */
    public function __construct(
        public Totals $totals,
        public array $bestFilm,
        public array $worstFilm,
        public array $mostDivisive,
        public array $mostAgreed,
        public ?MemberStats $mostActiveMember,
        public ?MemberStats $bestCurator,
    ) {
    }
}
