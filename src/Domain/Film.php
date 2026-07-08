<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Film
{
    public function __construct(
        public string $slug,
        public string $title,
    ) {
    }
}
