<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Score
{
    public const int MIN = 1;
    public const int MAX = 10;

    public function __construct(
        public int $value,
    ) {
        if ($value < self::MIN || $value > self::MAX) {
            throw new \InvalidArgumentException(sprintf('Score must be between %d and %d, got %d.', self::MIN, self::MAX, $value));
        }
    }
}
