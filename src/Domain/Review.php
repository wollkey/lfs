<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class Review
{
    public function __construct(
        public string $filmSlug,
        public string $memberUsername,
        public string $body,
        public ?string $writtenOn = null,
        public ReviewSource $source = ReviewSource::Manual,
        public ?int $telegramMessageId = null,
    ) {
        if (trim($body) === '') {
            throw new \InvalidArgumentException('A review cannot be empty.');
        }
    }
}
