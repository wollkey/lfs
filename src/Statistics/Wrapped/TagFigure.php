<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

final readonly class TagFigure
{
    public function __construct(
        public string $name,
        public int $films,
        public ?float $average = null,
    ) {
    }
}
