<?php

declare(strict_types=1);

namespace App\Reviewing;

use App\Telegram\Inbox\CapturedMessage;

final readonly class Candidate
{
    public function __construct(
        public CapturedMessage $message,
        public ?string $filmSlug,
        public ?string $memberUsername,
        public MatchedBy $matchedBy,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->filmSlug !== null && $this->memberUsername !== null;
    }

    public function withFilm(string $filmSlug, MatchedBy $matchedBy): self
    {
        return new self($this->message, $filmSlug, $this->memberUsername, $matchedBy);
    }

    public function withMember(string $memberUsername): self
    {
        return new self($this->message, $this->filmSlug, $memberUsername, $this->matchedBy);
    }
}
