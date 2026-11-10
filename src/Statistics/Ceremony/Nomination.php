<?php

declare(strict_types=1);

namespace App\Statistics\Ceremony;

final readonly class Nomination
{
    /**
     * @param list<Nominee> $nominees
     */
    public function __construct(
        public string $title,
        public string $lead,
        public array $nominees,
    ) {
    }
}
