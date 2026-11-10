<?php

declare(strict_types=1);

namespace App\Statistics\Ceremony;

final readonly class Nominee
{
    public function __construct(
        public string $name,
        public ?string $detail,
        public ?string $slug,
        public ?string $value,
        public bool $winner,
    ) {
    }
}
