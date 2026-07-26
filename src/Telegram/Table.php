<?php

declare(strict_types=1);

namespace App\Telegram;

final readonly class Table
{
    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
    ) {
    }
}
