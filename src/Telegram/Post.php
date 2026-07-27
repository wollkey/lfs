<?php

declare(strict_types=1);

namespace App\Telegram;

final readonly class Post
{
    /**
     * @param list<Cell> $links
     */
    public function __construct(
        public string $title,
        public ?Table $table = null,
        public ?string $intro = null,
        public array $links = [],
    ) {
    }
}
