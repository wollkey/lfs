<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Round
{
    public function __construct(
        public int $number,
        public ?string $startedOn = null,
        public ?string $endedOn = null,
    ) {
    }
}
