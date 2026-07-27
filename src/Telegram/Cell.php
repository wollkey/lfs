<?php

declare(strict_types=1);

namespace App\Telegram;

final readonly class Cell
{
    public function __construct(
        public string $text,
        public ?string $url = null,
    ) {
    }
}
