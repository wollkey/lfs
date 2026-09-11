<?php

declare(strict_types=1);

namespace App\Telegram\Inbox;

final readonly class LogReader
{
    public function __construct(
        private string $directory,
    ) {
    }

    /**
     * @return list<CapturedMessage>
     */
    public function all(): array
    {
        $messages = [];
        foreach ($this->files() as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $row = json_decode($line, true);
                if (is_array($row) && isset($row['kind'], $row['updateId'])) {
                    $messages[] = CapturedMessage::fromArray($row);
                }
            }
        }

        usort($messages, static fn (CapturedMessage $a, CapturedMessage $b): int => $a->date <=> $b->date);

        return $messages;
    }

    /**
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->directory.'/updates-*.jsonl') ?: [];
        sort($files);

        return $files;
    }
}
