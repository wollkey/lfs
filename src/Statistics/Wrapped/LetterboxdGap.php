<?php

declare(strict_types=1);

namespace App\Statistics\Wrapped;

/**
 * Our score for a film next to Letterboxd's, both on the club's 1-10 scale.
 */
final readonly class LetterboxdGap
{
    public function __construct(
        public string $slug,
        public string $title,
        public float $ours,
        public float $letterboxd,
    ) {
    }
}
