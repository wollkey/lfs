<?php

declare(strict_types=1);

namespace App\Reviewing;

final readonly class Verdict
{
    public function __construct(
        public bool $isReview,
        public ?string $filmSlug,
    ) {
    }
}
