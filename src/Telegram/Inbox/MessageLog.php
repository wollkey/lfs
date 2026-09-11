<?php

declare(strict_types=1);

namespace App\Telegram\Inbox;

use App\Telegram\Exception\LogException;

final readonly class MessageLog
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function append(CapturedMessage $message): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new LogException("Cannot create the capture directory: {$this->directory}.");
        }

        $file = $this->fileFor($message->date);
        $line = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($file, $line.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new LogException("Cannot write to the capture log: {$file}.");
        }
    }

    public function fileFor(int $date): string
    {
        return sprintf('%s/updates-%s.jsonl', $this->directory, gmdate('Y-m', $date));
    }
}
